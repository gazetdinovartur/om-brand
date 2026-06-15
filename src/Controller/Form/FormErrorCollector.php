<?php

namespace App\Controller\Form;

use Symfony\Component\Form\FormInterface;

final class FormErrorCollector
{
    /**
     * @return array<string, list<string>>
     */
    public static function collect(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            if (!$origin instanceof FormInterface || $origin->getName() === $form->getName()) {
                $errors['_form'][] = $error->getMessage();

                continue;
            }

            $errors[$origin->getName()][] = $error->getMessage();
        }

        return $errors;
    }
}
