<?php

declare(strict_types=1);

namespace App\Admin\Concern;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

trait CompactIndexActionsTrait
{
    private function iconIndexAction(Action $action, string $title, ?string $icon = null): Action
    {
        if (null !== $icon) {
            $action->setIcon($icon);
        }

        return $action
            ->setLabel(false)
            ->setHtmlAttributes([
                'title' => $title,
                'aria-label' => $title,
            ]);
    }

    private function compactBuiltinIndexActions(Actions $actions): Actions
    {
        foreach ([
            Action::DETAIL => ['Просмотр', 'fa fa-eye'],
            Action::EDIT => ['Редактировать', 'fa fa-pen'],
            Action::DELETE => ['Удалить', 'fa fa-trash-alt'],
        ] as $name => [$title, $icon]) {
            try {
                $actions->update(
                    Crud::PAGE_INDEX,
                    $name,
                    fn (Action $action): Action => $this->iconIndexAction($action, $title, $icon),
                );
            } catch (\InvalidArgumentException) {
            }
        }

        return $actions;
    }
}
