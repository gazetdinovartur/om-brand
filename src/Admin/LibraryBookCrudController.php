<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\LibraryBook;
use App\Form\LibraryBookImportType;
use App\Service\LibraryBookImporter;
use App\Service\LibraryImportUploadStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LibraryBookCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly LibraryBookImporter $importer,
        private readonly LibraryImportUploadStorage $uploadStorage,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return LibraryBook::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Книга')
            ->setEntityLabelInPlural('Библиотека')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Библиотека')
            ->setSearchFields(['title', 'author', 'slug'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $upload = Action::new('upload', 'Загрузить книгу', 'fa fa-upload')
            ->linkToCrudAction('upload')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-primary');

        $replace = Action::new('replaceBook', 'Заменить файл', 'fa fa-sync')
            ->linkToCrudAction('replaceBook')
            ->setHtmlAttributes(['title' => 'Заменить файл', 'aria-label' => 'Заменить файл']);

        $openSite = Action::new('openSite', 'Открыть', 'fa fa-external-link-alt')
            ->linkToRoute('web_library_continue', static fn (LibraryBook $book): array => [
                'bookSlug' => $book->getSlug(),
            ])
            ->setHtmlAttributes([
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
                'title' => 'Открыть на сайте',
                'aria-label' => 'Открыть на сайте',
            ]);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::DETAIL, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, $upload)
            ->add(Crud::PAGE_INDEX, $openSite)
            ->add(Crud::PAGE_INDEX, $replace)
            ->reorder(Crud::PAGE_INDEX, ['openSite', 'replaceBook']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Название');
        yield TextField::new('author', 'Автор');
        yield TextField::new('slug', 'Slug');
        yield IntegerField::new('chapterCount', 'Глав')->onlyOnIndex()->setSortable(false);
        yield DateTimeField::new('updatedAt', 'Обновлена')->onlyOnIndex();
        yield DateTimeField::new('createdAt', 'Создана')->onlyOnDetail();
    }

    public function createEntity(string $entityFqcn): never
    {
        throw $this->createAccessDeniedException('Создавайте книгу через «Загрузить книгу».');
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): never
    {
        throw $this->createAccessDeniedException('Редактирование глав вручную недоступно. Используйте «Заменить файл».');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): never
    {
        throw $this->createAccessDeniedException('Удаление книги пока недоступно.');
    }

    #[AdminRoute(path: '/upload', name: 'upload')]
    public function upload(Request $request): Response
    {
        $form = $this->createImportForm(null, false);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleImport($form, allowReplaceForced: false);
        }

        return $this->render('admin/library/import.html.twig', [
            'page_title' => 'Загрузить книгу',
            'form' => $form,
            'mode' => 'upload',
            'book' => null,
            'index_url' => $this->indexUrl(),
        ]);
    }

    #[AdminRoute(path: '/{entityId}/replace', name: 'replace')]
    public function replaceBook(AdminContext $context, Request $request): Response
    {
        $book = $context->getEntity()->getInstance();
        if (!$book instanceof LibraryBook) {
            throw $this->createNotFoundException('Книга не найдена.');
        }

        $form = $this->createImportForm($book->getSlug(), true);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleImport($form, allowReplaceForced: true, fixedSlug: $book->getSlug());
        }

        return $this->render('admin/library/import.html.twig', [
            'page_title' => 'Заменить файл',
            'form' => $form,
            'mode' => 'replace',
            'book' => $book,
            'index_url' => $this->indexUrl(),
        ]);
    }

    private function createImportForm(?string $fixedSlug, bool $replaceDefault): FormInterface
    {
        return $this->createForm(LibraryBookImportType::class, null, [
            'fixed_slug' => $fixedSlug,
            'replace_default' => $replaceDefault,
        ]);
    }

    private function handleImport(
        FormInterface $form,
        bool $allowReplaceForced,
        ?string $fixedSlug = null,
    ): Response {
        /** @var UploadedFile|null $file */
        $file = $form->get('file')->getData();
        if (!$file instanceof UploadedFile) {
            $this->addFlash('danger', 'Выберите файл TXT или ZIP.');

            return $this->redirect($this->indexUrl());
        }

        $title = $this->optionalString($form->get('title')->getData());
        $author = $this->optionalString($form->get('author')->getData());
        $slug = $fixedSlug ?? $this->optionalString($form->get('slug')->getData());
        $allowReplace = $allowReplaceForced || (bool) $form->get('replace')->getData();

        $storedPath = null;
        try {
            $storedPath = $this->uploadStorage->store($file);
            $result = $this->importer->import(
                $storedPath,
                $title,
                $author,
                $slug,
                allowReplace: $allowReplace,
                sourceName: $file->getClientOriginalName(),
            );
            $this->addFlash('success', $result->message());
        } catch (FileException|\InvalidArgumentException|\RuntimeException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Не удалось импортировать книгу.');
        } finally {
            $this->uploadStorage->remove($storedPath);
        }

        return $this->redirect($this->indexUrl());
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    private function optionalString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
