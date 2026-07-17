<?php

namespace App\Form\Admin;

use App\Entity\CaseStudyImage;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
                'label' => 'Изображение',
                'upload_dir' => 'public/uploads/cases/gallery',
                'upload_filename' => '[uuid].[extension]',
                'download_path' => 'uploads/cases/gallery',
                'required' => true,
                'attr' => ['accept' => 'image/*'],
                'file_constraints' => [
                    new Image(maxSize: '8M'),
                ],
            ])
            ->add('caption', TextType::class, [
                'label' => 'Подпись',
                'required' => false,
                'attr' => ['placeholder' => 'Что на кадре'],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Порядок',
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
