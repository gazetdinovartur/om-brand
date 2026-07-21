<?php

namespace App\Form\Admin;

use App\Entity\CaseStudyImage;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class CaseStudyImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imagePath', FileUploadType::class, [
                'label' => 'Картинка',
                'upload_dir' => 'public/uploads/cases/gallery',
                'upload_filename' => '[uuid].[extension]',
                // trailing slash is required — EA concatenates path + filename
                'download_path' => 'uploads/cases/gallery/',
                'required' => true,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                    'class' => 'case-gallery-file-input',
                ],
                'file_constraints' => [
                    new Image(
                        maxSize: '8M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                        mimeTypesMessage: 'Загрузите JPG, PNG, WebP или GIF',
                    ),
                ],
            ])
            ->add('caption', TextType::class, [
                'label' => 'Подпись',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Что на кадре (необязательно)',
                    'class' => 'form-control case-gallery-caption',
                ],
            ])
            ->add('sortOrder', HiddenType::class, [
                'required' => false,
                'empty_data' => '0',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CaseStudyImage::class,
            'label' => false,
        ]);
    }
}
