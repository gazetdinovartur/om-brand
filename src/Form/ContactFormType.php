<?php

namespace App\Form;

use App\Enum\InquiryType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Универсальная форма связи на /contact. */
final class ContactFormType extends InquiryFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'inquiry_types' => InquiryType::contactOrdered(),
            'inquiry_type_label' => 'О чём речь',
        ]);
    }
}
