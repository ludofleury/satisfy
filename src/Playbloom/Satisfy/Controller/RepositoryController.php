<?php

namespace Playbloom\Satisfy\Controller;

use Playbloom\Satisfy\Form\Type\ComposerLockType;
use Playbloom\Satisfy\Form\Type\DeleteFormType;
use Playbloom\Satisfy\Form\Type\RepositoryType;
use Playbloom\Satisfy\Model\Repository;
use Playbloom\Satisfy\Service\LockProcessor;
use Playbloom\Satisfy\Service\Manager;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RepositoryController extends AbstractProtectedController
{
    /** @var Manager */
    private $manager;

    /** @var LockProcessor */
    private $lockProcessor;

    public function __construct(Manager $manager, LockProcessor $lockProcessor)
    {
        $this->manager = $manager;
        $this->lockProcessor = $lockProcessor;
    }

    public function indexAction(): Response
    {
        $this->checkAccess();
        $this->checkEnvironment();

        $config = $this->manager->getConfig();
        $repositories = $this->manager->getRepositories();
        $satisRepository = [
            'type' => 'composer',
            'url' => $config->getHomepage(),
        ];

        $config = [
            'repositories' => [$satisRepository],
        ];

        return $this->render('@PlaybloomSatisfy/home.html.twig', compact('config', 'repositories'));
    }

    public function newAction(Request $request): Response
    {
        $this->checkAccess();

        $repository = new Repository();
        $form = $this->createForm(
            RepositoryType::class,
            $repository
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->get(Manager::class)->add($form->getData());

                return $this->redirectToRoute('repository');
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('@PlaybloomSatisfy/new.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @return RedirectResponse|Response
     */
    public function uploadAction(Request $request): Response
    {
        $this->checkAccess();

        $form = $this->createForm(ComposerLockType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $lock = $form->get('file')->getData()->openFile();
                $this->lockProcessor->processFile($lock);

                return $this->redirectToRoute('repository');
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('@PlaybloomSatisfy/upload.html.twig', ['form' => $form->createView()]);
    }

    public function editAction(Request $request): Response
    {
        $this->checkAccess();
        $repository = $this->manager->findOneRepository($request->attributes->get('repository'));
        if (!$repository) {
            return $this->redirectToRoute('repository');
        }

        $form = $this->createForm(
            RepositoryType::class,
            clone $repository
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->manager->update($repository, $form->getData());

                return $this->redirectToRoute('repository');
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('@PlaybloomSatisfy/edit.html.twig', ['form' => $form->createView()]);
    }

    public function deleteAction(Request $request): Response
    {
        $this->checkAccess();
        $repository = $this->manager->findOneRepository($request->attributes->get('repository'));
        if (!$repository) {
            return $this->redirectToRoute('repository');
        }

        $form = $this->createForm(DeleteFormType::class);
        if (Request::METHOD_DELETE === $request->getMethod()) {
            try {
                $this->manager->delete($repository);

                return $this->redirectToRoute('repository');
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render(
            '@PlaybloomSatisfy/delete.html.twig',
            ['form' => $form->createView(), 'repository' => $repository]
        );
    }
}
