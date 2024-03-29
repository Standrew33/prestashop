<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShopBundle\Translation\Loader;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Translation;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * The user translated catalogue is stored in database.
 * This class is a helper to build the query for retrieving the translations.
 * They depend on parameters like locale, theme or domain.
 */
class DatabaseTranslationLoader implements LoaderInterface
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param mixed $resource
     * @param string $locale
     * @param string $domain
     * @param string|null $theme
     *
     * @return MessageCatalogue
     *
     * @todo: this method doesn't match the interface
     */
    public function load($resource, $locale, $domain = 'messages', $theme = null): MessageCatalogue
    {
        static $langs = [];
        $catalogue = new MessageCatalogue($locale);

        // do not try and load translations for a locale that cannot be saved to DB anyway
        if ($locale === 'default') {
            return $catalogue;
        }

        if (!array_key_exists($locale, $langs)) {
            $langs[$locale] = $this->entityManager->getRepository(Lang::class)->findOneBy(['locale' => $locale]);
        }

        if ($langs[$locale] === null) {
            return $catalogue;
        }

        /** @var EntityRepository $translationRepository */
        $translationRepository = $this->entityManager->getRepository(Translation::class);

        $queryBuilder = $translationRepository->createQueryBuilder('t');

        $this->addLangConstraint($queryBuilder, $langs[$locale]);

        $this->addThemeConstraint($queryBuilder, $theme);

        $this->addDomainConstraint($queryBuilder, $domain);

        $translations = $queryBuilder
            ->getQuery()
            ->getResult();

        /** @var Translation $translation */
        foreach ($translations as $translation) {
            $catalogue->set($translation->getKey(), $translation->getTranslation(), $translation->getDomain());
        }

        return $catalogue;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param Lang $currentLang
     */
    private function addLangConstraint(QueryBuilder $queryBuilder, Lang $currentLang)
    {
        $queryBuilder->andWhere('t.lang =:lang')
            ->setParameter('lang', $currentLang);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string|null $theme
     */
    private function addThemeConstraint(QueryBuilder $queryBuilder, $theme)
    {
        if (null === $theme) {
            $queryBuilder->andWhere('t.theme IS NULL');
        } else {
            $queryBuilder
                ->andWhere('t.theme = :theme')
                ->setParameter('theme', $theme);
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $domain
     */
    private function addDomainConstraint(QueryBuilder $queryBuilder, $domain)
    {
        if ($domain !== '*') {
            $queryBuilder->andWhere('REGEXP(t.domain, :domain) = true')
                ->setParameter('domain', $domain);
        }
    }
}
