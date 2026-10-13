(() => {
    'use strict';

    const decodeBase64Url = (value) => {
        const normalized = String(value).replace(/-/g, '+').replace(/_/g, '/');
        const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);
        const binary = atob(padded);
        const bytes = new Uint8Array(binary.length);
        for (let index = 0; index < binary.length; index += 1) {
            bytes[index] = binary.charCodeAt(index);
        }
        return bytes;
    };

    const encodeBase64Url = (value) => {
        const bytes = value instanceof Uint8Array ? value : new Uint8Array(value);
        let binary = '';
        for (let index = 0; index < bytes.length; index += 1) {
            binary += String.fromCharCode(bytes[index]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    };

    const prepareDescriptors = (items = []) => items.map((item) => ({
        ...item,
        id: decodeBase64Url(item.id),
    }));

    const prepareCreationOptions = (data) => ({
        ...data,
        challenge: decodeBase64Url(data.challenge),
        user: {
            ...data.user,
            id: decodeBase64Url(data.user.id),
        },
        excludeCredentials: prepareDescriptors(data.excludeCredentials),
    });

    const prepareRequestOptions = (data) => ({
        ...data,
        challenge: decodeBase64Url(data.challenge),
        allowCredentials: prepareDescriptors(data.allowCredentials),
    });

    const serializeCredential = (credential) => {
        const response = credential.response;
        const serializedResponse = {
            clientDataJSON: encodeBase64Url(response.clientDataJSON),
        };

        if ('attestationObject' in response) {
            serializedResponse.attestationObject = encodeBase64Url(response.attestationObject);
            serializedResponse.transports = typeof response.getTransports === 'function'
                ? response.getTransports()
                : [];
        } else if ('authenticatorData' in response && 'signature' in response) {
            serializedResponse.authenticatorData = encodeBase64Url(response.authenticatorData);
            serializedResponse.signature = encodeBase64Url(response.signature);
            serializedResponse.userHandle = response.userHandle
                ? encodeBase64Url(response.userHandle)
                : null;
        }

        return {
            id: encodeBase64Url(credential.rawId),
            rawId: encodeBase64Url(credential.rawId),
            type: credential.type,
            response: serializedResponse,
            clientExtensionResults: credential.getClientExtensionResults(),
            authenticatorAttachment: credential.authenticatorAttachment || null,
        };
    };

    const postJson = async (url, payload, signal = undefined) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
            signal,
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || data?.status === 'error') {
            throw new Error(data?.errorMessage || 'Сервер отклонил операцию.');
        }
        return data;
    };

    const readableError = (error) => {
        if (error?.name === 'NotAllowedError') {
            return 'Операция отменена или подходящий ключ не найден.';
        }
        if (error?.name === 'SecurityError') {
            return 'Ключ доступа работает только на настроенном HTTPS-домене.';
        }
        if (error?.name === 'InvalidStateError') {
            return 'Этот ключ уже добавлен.';
        }
        return 'Не удалось выполнить операцию с ключом доступа.';
    };

    const setStatus = (root, message, isError = false) => {
        const status = root.querySelector('[data-passkey-status]');
        if (!(status instanceof HTMLElement)) {
            return;
        }
        status.textContent = message;
        status.classList.toggle('text-danger', isError);
    };

    const supportsPasskeys = () => (
        'PublicKeyCredential' in window
        && navigator.credentials
        && typeof navigator.credentials.get === 'function'
        && typeof navigator.credentials.create === 'function'
    );

    const initLogin = (root) => {
        const button = root.querySelector('[data-passkey-login-button]');
        const optionsUrl = root.dataset.optionsUrl || '';
        const resultUrl = root.dataset.resultUrl || '';
        const redirectUrl = root.dataset.redirectUrl || '/admin';
        let conditionalController = null;

        if (!(button instanceof HTMLButtonElement) || !optionsUrl || !resultUrl) {
            return;
        }
        if (!supportsPasskeys()) {
            root.hidden = true;
            return;
        }
        root.hidden = false;

        const authenticate = async (conditional = false) => {
            const controller = new AbortController();
            if (conditional) {
                conditionalController = controller;
            }
            const options = await postJson(optionsUrl, {}, controller.signal);
            const credential = await navigator.credentials.get({
                publicKey: prepareRequestOptions(options),
                mediation: conditional ? 'conditional' : 'optional',
                signal: controller.signal,
            });
            if (!(credential instanceof PublicKeyCredential)) {
                throw new Error('Credential missing');
            }
            await postJson(resultUrl, serializeCredential(credential), controller.signal);
            window.location.assign(redirectUrl);
        };

        button.addEventListener('click', async () => {
            conditionalController?.abort();
            conditionalController = null;
            button.disabled = true;
            setStatus(root, 'Подтвердите вход на устройстве…');
            try {
                await authenticate(false);
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    setStatus(root, readableError(error), true);
                }
            } finally {
                button.disabled = false;
            }
        });

        if (
            root.dataset.conditional === '1'
            && typeof PublicKeyCredential.isConditionalMediationAvailable === 'function'
        ) {
            void PublicKeyCredential.isConditionalMediationAvailable().then((available) => {
                if (!available) {
                    return;
                }
                return authenticate(true).catch((error) => {
                    if (error?.name !== 'AbortError' && error?.name !== 'NotAllowedError') {
                        setStatus(root, readableError(error), true);
                    }
                });
            });
        }
    };

    const initRegistration = (root) => {
        const button = root.querySelector('[data-passkey-register-button]');
        const optionsUrl = root.dataset.optionsUrl || '';
        const resultUrl = root.dataset.resultUrl || '';

        if (!(button instanceof HTMLButtonElement) || !optionsUrl || !resultUrl) {
            return;
        }
        if (!supportsPasskeys()) {
            button.disabled = true;
            setStatus(root, 'Этот браузер не поддерживает ключи доступа.', true);
            return;
        }

        button.addEventListener('click', async () => {
            button.disabled = true;
            setStatus(root, 'Подтвердите создание ключа на устройстве…');
            try {
                const options = await postJson(optionsUrl, {});
                const credential = await navigator.credentials.create({
                    publicKey: prepareCreationOptions(options),
                });
                if (!(credential instanceof PublicKeyCredential)) {
                    throw new Error('Credential missing');
                }
                await postJson(resultUrl, serializeCredential(credential));
                setStatus(root, 'Ключ добавлен. Обновляем список…');
                window.location.reload();
            } catch (error) {
                setStatus(root, readableError(error), true);
                button.disabled = false;
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-passkey-login]').forEach((root) => {
            if (root instanceof HTMLElement) {
                initLogin(root);
            }
        });
        document.querySelectorAll('[data-passkey-register]').forEach((root) => {
            if (root instanceof HTMLElement) {
                initRegistration(root);
            }
        });
    });
})();
