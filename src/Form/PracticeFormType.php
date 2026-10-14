<?php

namespace App\Form;

use App\Enum\InquiryType;
use App\Practice\PracticeFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Заявка на странице «Регуляция и регулярность». */
final class PracticeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $authenticated = (bool) $options['authenticated'];
        $accountHasEmail = (bool) $options['account_has_email'];
        $showEmailField = !$authenticated || !$accountHasEmail;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Как к тебе обращаться',
                'data' => $options['default_name'] ?: null,
                'constraints' => [
                    new NotBlank(message: 'Напиши, как к тебе обращаться'),
                    new Length(max: 120),
                ],
            ]);

        if ($showEmailField) {
            $emailConstraints = [
                new Length(max: 180),
                new Email(message: 'Укажи корректный email'),
            ];
            if (!$authenticated) {
                $emailConstraints[] = new NotBlank(message: 'Укажи email');
            }

            $builder->add('email', EmailType::class, [
                'label' => $authenticated ? 'Email для чека и писем' : 'Email',
                'required' => !$authenticated,
                'data' => $options['default_email'] ?: null,
                'attr' => [
                    'autocomplete' => 'email',
                ],
                'constraints' => $emailConstraints,
            ]);
        }

        $builder
            ->add('format', ChoiceType::class, [
                'mapped' => false,
                'label' => 'Как хочешь участвовать',
                'choices' => [
                    'Первая встреча — бесплатно' => PracticeFormat::First->value,
                    'Разовая встреча — 222 ₽' => PracticeFormat::Single->value,
                    'Цикл из 4 встреч — 555 ₽' => PracticeFormat::Cycle->value,
                ],
                'constraints' => [
                    new NotBlank(message: 'Выбери формат участия'),
                ],
            ])
            ->add('inquiryType', HiddenType::class, [
                'data' => InquiryType::Practice->value,
                'empty_data' => InquiryType::Practice->value,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Если есть, что сказать заранее',
                'required' => false,
                'empty_data' => '',
                'attr' => ['rows' => 4],
                'constraints' => [
                    new Length(max: 10000),
                ],
            ])
            ->add('website', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                    'class' => 'inquiry-honeypot',
                ],
            ]);

        if (!$authenticated) {
            $builder->add('consent', CheckboxType::class, [
                'mapped' => false,
                'required' => true,
                'label' => false,
                'constraints' => [
                    new IsTrue(message: 'Нужно согласие на обработку персональных данных'),
                ],
            ]);
        }

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($authenticated, $accountHasEmail): void {
            $form = $event->getForm();
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            $rawType = $data['inquiryType'] ?? InquiryType::Practice->value;
            $data['inquiryType'] = $rawType instanceof InquiryType
                ? $rawType
                : InquiryType::tryFrom((string) $rawType) ?? InquiryType::Practice;

            $format = PracticeFormat::tryFrom((string) $form->get('format')->getData());
            $message = trim((string) ($data['message'] ?? ''));
            if ($format instanceof PracticeFormat) {
                $data['message'] = '' === $message
                    ? $format->label()
                    : $format->label()."\n\n".$message;
            }

            if ($authenticated && $accountHasEmail) {
                $data['email'] = mb_strtolower(trim((string) ($form->getConfig()->getOption('default_email') ?? '')));
            } else {
                $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
                $data['email'] = $email;

                if (
                    $format instanceof PracticeFormat
                    && $format->requiresPayment()
                    && ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL))
                ) {
                    $emailField = $form->has('email') ? $form->get('email') : $form;
                    $emailField->addError(new FormError('Для оплаты укажи email — на него придёт чек и ссылка'));
                }
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'authenticated' => false,
            'account_has_email' => false,
            'default_name' => '',
            'default_email' => '',
        ]);
        $resolver->setAllowedTypes('authenticated', 'bool');
        $resolver->setAllowedTypes('account_has_email', 'bool');
        $resolver->setAllowedTypes('default_name', 'string');
        $resolver->setAllowedTypes('default_email', 'string');
    }
}
