<?php

namespace App\Form;

use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Validation\ContactValueValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class InquiryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<InquiryType> $inquiryTypes */
        $inquiryTypes = $options['inquiry_types'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Как к вам обращаться',
                'constraints' => [
                    new NotBlank(message: 'Напишите, как к вам обращаться'),
                    new Length(max: 120),
                ],
            ])
            ->add('contactType', ChoiceType::class, [
                'label' => 'Как удобнее связаться',
                'choices' => ContactType::cases(),
                'choice_label' => static fn (ContactType $type) => $type->label(),
                'choice_value' => static fn (?ContactType $type) => $type?->value,
                'attr' => ['data-contact-type' => ''],
            ])
            ->add('contact', TextType::class, [
                'label' => 'Контакт',
                'attr' => [
                    'data-contact-input' => '',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'Укажите, как с вами связаться'),
                    new Length(max: 255),
                ],
            ])
            ->add('inquiryType', ChoiceType::class, [
                'label' => $options['inquiry_type_label'],
                'choices' => $inquiryTypes,
                'choice_label' => static fn (InquiryType $type) => $type->label(),
                'choice_value' => static fn (?InquiryType $type) => $type?->value,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Ваш текст в свободной форме',
                'required' => false,
                'empty_data' => '',
                'attr' => ['rows' => 5],
                'constraints' => [
                    new Length(max: 10000),
                ],
            ])
            ->add('attachment', FileType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'text/plain',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        mimeTypesMessage: 'Можно прикрепить фото или текстовый документ',
                    ),
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
            ])
            ->add('consent', CheckboxType::class, [
                'mapped' => false,
                'required' => true,
                'label' => false,
                'constraints' => [
                    new IsTrue(message: 'Нужно согласие на обработку персональных данных'),
                ],
            ]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            $contactType = $data['contactType'] ?? null;
            $contact = trim((string) ($data['contact'] ?? ''));

            if (!$contactType instanceof ContactType || '' === $contact) {
                return;
            }

            $error = ContactValueValidator::validate($contact, $contactType);
            if (null !== $error) {
                $event->getForm()->get('contact')->addError(new FormError($error));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inquiry_types' => InquiryType::developmentOrdered(),
            'inquiry_type_label' => 'О чём запрос',
        ]);
        $resolver->setAllowedTypes('inquiry_types', 'array');
        $resolver->setAllowedTypes('inquiry_type_label', 'string');
    }
}
