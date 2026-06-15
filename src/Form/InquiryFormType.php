<?php

namespace App\Form;

use App\Enum\ContactType;
use App\Enum\InquiryType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class InquiryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
            ])
            ->add('contact', TextType::class, [
                'label' => 'Контакт',
                'constraints' => [
                    new NotBlank(message: 'Укажите, как с вами связаться'),
                    new Length(max: 255),
                ],
            ])
            ->add('inquiryType', ChoiceType::class, [
                'label' => 'О чём запрос',
                'choices' => InquiryType::ordered(),
                'choice_label' => static fn (InquiryType $type) => $type->label(),
                'choice_value' => static fn (?InquiryType $type) => $type?->value,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Расскажите о задаче',
                'attr' => ['rows' => 5],
                'constraints' => [
                    new NotBlank(message: 'Расскажите хотя бы пару строк о задаче'),
                    new Length(min: 10, minMessage: 'Напишите чуть подробнее — хотя бы пару предложений'),
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
            ]);
    }
}
