<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;

final class LibraryBookImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fixedSlug = $options['fixed_slug'];
        $replaceDefault = (bool) $options['replace_default'];

        $builder->add('file', FileType::class, [
            'label' => 'Файл книги',
            'mapped' => false,
            'required' => true,
            'help' => 'До 20 МБ. TXT, MD, FB2, EPUB или ZIP с txt/md/fb2.',
            'attr' => ['class' => 'form-control'],
            'label_attr' => ['class' => 'form-label'],
            'help_attr' => ['class' => 'form-text'],
            'constraints' => [
                new File(
                    maxSize: '20M',
                    // Ассоциативная форма: MIME для fb2 часто text/xml, а не application/x-fictionbook.
                    extensions: [
                        'txt' => ['text/plain', 'application/octet-stream'],
                        'md' => ['text/plain', 'text/markdown', 'text/x-markdown', 'application/octet-stream'],
                        'markdown' => ['text/plain', 'text/markdown', 'text/x-markdown', 'application/octet-stream'],
                        'fb2' => [
                            'text/xml',
                            'application/xml',
                            'application/x-fictionbook',
                            'application/x-fictionbook+xml',
                            'application/octet-stream',
                        ],
                        'epub' => ['application/epub+zip', 'application/zip', 'application/octet-stream'],
                        'zip' => [
                            'application/zip',
                            'application/x-zip',
                            'application/x-zip-compressed',
                            'application/octet-stream',
                        ],
                    ],
                    extensionsMessage: 'Поддерживаются TXT, MD, FB2, EPUB и ZIP с txt/md/fb2.',
                    mimeTypesMessage: 'Поддерживаются TXT, MD, FB2, EPUB и ZIP с txt/md/fb2.',
                ),
            ],
        ]);

        $builder->add('title', TextType::class, [
            'label' => 'Название',
            'required' => false,
            'help' => 'Пусто — взять из файла.',
            'attr' => ['class' => 'form-control', 'placeholder' => 'Как в файле'],
            'label_attr' => ['class' => 'form-label'],
            'help_attr' => ['class' => 'form-text'],
            'constraints' => [new Length(max: 255)],
        ]);

        $builder->add('author', TextType::class, [
            'label' => 'Автор',
            'required' => false,
            'help' => 'Пусто — взять из файла.',
            'attr' => ['class' => 'form-control', 'placeholder' => 'Как в файле'],
            'label_attr' => ['class' => 'form-label'],
            'help_attr' => ['class' => 'form-text'],
            'constraints' => [new Length(max: 180)],
        ]);

        $builder->add('slug', TextType::class, [
            'label' => 'Slug',
            'required' => false,
            'disabled' => null !== $fixedSlug,
            'data' => $fixedSlug,
            'help' => null !== $fixedSlug
                ? 'При замене slug не меняется.'
                : 'Пусто — из названия. Латиница, без пробелов.',
            'attr' => ['class' => 'form-control', 'placeholder' => 'dekoncentratsiya'],
            'label_attr' => ['class' => 'form-label'],
            'help_attr' => ['class' => 'form-text'],
            'constraints' => [new Length(max: 120)],
        ]);

        $builder->add('replace', CheckboxType::class, [
            'label' => 'Заменить, если книга с таким slug уже есть',
            'required' => false,
            'data' => $replaceDefault,
            'attr' => ['class' => 'form-check-input'],
            'label_attr' => ['class' => 'form-check-label'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'fixed_slug' => null,
            'replace_default' => false,
        ]);
        $resolver->setAllowedTypes('fixed_slug', ['null', 'string']);
        $resolver->setAllowedTypes('replace_default', 'bool');
    }
}
