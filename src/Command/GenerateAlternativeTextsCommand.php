<?php

namespace CDZAlternativeTextGenerator\Command;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateAlternativeTextsCommand extends Command
{
    protected static $defaultName = 'cdz:generate-alternative-texts';
    protected static $defaultDescription = 'Generates alternative texts for products and categories';

    private EntityRepositoryInterface $productRepository;
    private EntityRepositoryInterface $categoryRepository;
    private EntityRepositoryInterface $mediaTranslationRepository;

    public function __construct(EntityRepositoryInterface $productRepository,
                                EntityRepositoryInterface $categoryRepository,
                                EntityRepositoryInterface $mediaTranslationRepository)
    {
        parent::__construct();
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->mediaTranslationRepository = $mediaTranslationRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = Context::createDefaultContext();

        $this->generateProductAlternativeTexts($io, $context);
        $io->success('Alternative texts for products have been generated successfully.');

        $this->generateCategoryAlternativeTexts($io, $context);
        $io->success('Alternative texts for categories have been generated successfully.');

        return Command::SUCCESS;
    }

    private function generateProductAlternativeTexts(SymfonyStyle $io, Context $context ): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('translations');
        $criteria->addAssociation('media.media.translations');
        $products = $this->productRepository->search($criteria, $context);
        /** @var ProductEntity $product */
        $progressBar = $io->createProgressBar(count($products));
        $progressBar->setFormat(
            "%current%/%max% [%bar%] %percent:3s%%\n\xE2\x8F\xB0 %estimated:-20s% %memory:20s% "
        );
        $progressBar->start();
        foreach ($products as $product) {
            //Get translated names + languageID
            foreach ($product->getTranslations() as $translation) {
                foreach ($product->getMedia() as $productMedia) {
                    $media = $productMedia->getMedia();
                    if ($media != null) {
                        /** @var MediaTranslationEntity $mediaTranslation */
                        $translationFound = false;
                        foreach ($media->getTranslations() as $mediaTranslation) {
                            if($mediaTranslation->getLanguageId() !== $translation->getLanguageId()) {
                                continue;
                            }

                            $translationFound = true;

                            if ($mediaTranslation->getAlt() == null || $mediaTranslation->getAlt() != $translation->getName()) {
                                $this->mediaTranslationRepository->update([
                                    [
                                        'mediaId' => $media->getId(),
                                        'languageId' => $translation->getLanguageId(),
                                        'alt' => $translation->getName()
                                    ]
                                ], $context);
                            }
                        }

                        if (!$translationFound) {
                            $this->mediaTranslationRepository->create([
                                [
                                    'mediaId' => $media->getId(),
                                    'languageId' => $translation->getLanguageId(),
                                    'alt' => $translation->getName()
                                ]
                            ]
                            , $context);
                        }
                    }
                 }

                }
            $progressBar->advance();

        }

        $progressBar->finish();

        }

        private function generateCategoryAlternativeTexts(SymfonyStyle $io, Context $context ): void
        {
            $criteria = new Criteria();
            $criteria->addAssociation('media');
            $criteria->addAssociation('translations');
            $criteria->addAssociation('media.translations');
            $categories = $this->categoryRepository->search($criteria, $context);
            $progressBar = $io->createProgressBar(count($categories));
            $progressBar->setFormat(
                "%current%/%max% [%bar%] %percent:3s%%\n\xE2\x8F\xB0 %estimated:-20s% %memory:20s% "
            );
            $progressBar->start();

            /** @var CategoryEntity $category */
            foreach ($categories as $category) {

                foreach ($category->getTranslations() as $translation) {

                /** @var MediaEntity $media */
                $media = $category->getMedia();

                if ($media != null) {
                    /** @var MediaTranslationEntity $mediaTranslation */
                    $translationFound = false;
                    foreach ($media->getTranslations() as $mediaTranslation) {
                        if($mediaTranslation->getLanguageId() !== $translation->getLanguageId()) {
                            continue;
                        }

                        $translationFound = true;

                        if ($mediaTranslation->getAlt() == null || $mediaTranslation->getAlt() != $translation->getName()) {
                            $this->mediaTranslationRepository->update([
                                [
                                    'mediaId' => $media->getId(),
                                    'languageId' => $translation->getLanguageId(),
                                    'alt' => $translation->getName()
                                ]
                            ], $context);
                        }
                    }

                    if (!$translationFound) {
                        $this->mediaTranslationRepository->create([
                                [
                                    'mediaId' => $media->getId(),
                                    'languageId' => $translation->getLanguageId(),
                                    'alt' => $translation->getName()
                                ]
                            ]
                            , $context);
                    }
                }

                }
                $progressBar->advance();

            }

            $progressBar->finish();

        }


}