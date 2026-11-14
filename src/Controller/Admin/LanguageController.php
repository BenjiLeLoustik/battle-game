<?php

namespace App\Controller\Admin;

use App\Admin\Translation\TranslationCatalog;
use App\Entity\Country;
use App\Repository\CountryRepository;
use App\Repository\UserRepository;
use App\Shared\Content\ContentTranslationManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Translation\Exception\TranslationException;
use NeoPHP\Package\Translation\TranslationManagerInterface;

#[Route('/languages', name: 'language_')]
class LanguageController extends AbstractAdminController
{
    private const LOCALE = '[a-z]{2}';

    public function __construct(protected TranslationCatalog $catalog)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CountryRepository $countries, UserRepository $users, ContentTranslationManager $contents): Response
    {
        $languages = [];

        foreach ($this->catalog->locales() as $locale) {
            $stats = $this->catalog->stats($locale);
            $languages[$locale] = [
                'stats' => $stats,
                'total' => array_sum(array_column($stats, 'total')),
                'done' => array_sum(array_column($stats, 'done')),
                'lock' => $this->catalog->holder($locale),
            ];
        }

        return $this->render('@admin/pages/language/index.html.twig', [
            'languages' => $languages,
            'reference' => TranslationCatalog::REFERENCE,
            'countries' => array_map(fn (Country $country): array => [
                'country' => $country,
                'users' => $users->count(['country' => $country->getCode()]),
                'names' => $contents->values($country),
            ], $countries->findOrdered()),
            'locales' => $this->catalog->locales(),
            'targets' => $contents->targets(),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[Csrf('admin-languages')]
    public function add(Request $request, TranslationManagerInterface $translator): Response
    {
        $locale = strtolower(trim((string) $request->request->get('locale')));

        if (!preg_match('/^' . self::LOCALE . '$/', $locale) || !$this->known($translator, $locale)) {
            $this->addFlash('error', 'admin.languages.unknown');

            return $this->redirectToRoute('admin_language_index');
        }

        $this->catalog->add($locale);
        $this->audit('translation.save', $locale);

        return $this->redirectToRoute('admin_language_index');
    }

    #[Route('/{locale}/delete', name: 'delete', methods: ['POST'], requirements: ['locale' => self::LOCALE])]
    #[Csrf('admin-languages')]
    public function delete(string $locale, CountryRepository $countries): Response
    {
        if ($locale === TranslationCatalog::REFERENCE || !in_array($locale, $this->catalog->locales(), true)) {
            throw $this->createNotFoundException();
        }

        $connection = $countries->getOrm()->getConnection();
        $connection->executeStatement('DELETE FROM content_translation WHERE locale = :locale', ['locale' => $locale]);
        $connection->executeStatement('UPDATE country SET locale = :reference WHERE locale = :locale', ['reference' => TranslationCatalog::REFERENCE, 'locale' => $locale]);
        $this->catalog->remove($locale);
        $this->audit('translation.delete', $locale);

        return $this->done('admin_language_index', [], 'admin.flash.deleted');
    }

    #[Route('/{locale}', name: 'translate', methods: ['GET', 'POST'], requirements: ['locale' => self::LOCALE])]
    #[Csrf('admin-languages')]
    public function work(string $locale, Request $request): Response
    {
        if (!in_array($locale, $this->catalog->locales(), true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->catalog->lock($locale, $this->getUser())) {
            $this->addFlash('error', 'admin.languages.locked');

            return $this->redirectToRoute('admin_language_index');
        }

        $domains = $this->catalog->domains();
        $domain = in_array($request->query->get('domain'), $domains, true) ? $request->query->get('domain') : $domains[0];
        $skip = max(0, (int) $request->query->get('skip'));

        if ($request->getMethod() === 'POST') {
            $key = (string) $request->request->get('key');
            $value = trim((string) $request->request->get('value'));

            if ($value !== '' && array_key_exists($key, $this->catalog->messages($domain, TranslationCatalog::REFERENCE))) {
                $this->catalog->save($domain, $locale, $key, $value);
            }

            if ($request->request->has('edited')) {
                $this->addFlash('success', 'admin.flash.saved');
            }

            return $this->redirectToRoute('admin_language_translate', ['locale' => $locale, 'domain' => $domain, 'skip' => $skip]);
        }

        $missing = $this->catalog->missing($domain, $locale);
        $reference = $this->catalog->messages($domain, TranslationCatalog::REFERENCE);
        $translated = array_diff_key(array_intersect_key($this->catalog->messages($domain, $locale), $reference), array_flip($missing));
        $edit = (string) $request->query->get('edit');
        $overrides = array_intersect_key($this->catalog->overrides($domain, $locale), $this->catalog->base($domain, $locale));
        $key = array_key_exists($edit, $translated) ? $edit : $missing[$skip] ?? null;

        return $this->render('@admin/pages/language/translate.html.twig', [
            'locale' => $locale,
            'domain' => $domain,
            'domains' => array_map(fn (string $name): array => ['name' => $name, 'left' => count($this->catalog->missing($name, $locale))], $domains),
            'key' => $key,
            'source' => $key === null ? null : $reference[$key],
            'value' => $key === null ? '' : $translated[$key] ?? '',
            'editing' => $key === $edit,
            'entries' => array_map(fn (string $name): array => ['key' => $name, 'source' => $reference[$name], 'value' => $translated[$name], 'modified' => array_key_exists($name, $overrides)], array_keys($translated)),
            'skip' => $skip,
            'left' => count($missing),
            'total' => count($reference),
            'reference' => TranslationCatalog::REFERENCE,
        ]);
    }

    #[Route('/{locale}/revert', name: 'revert', methods: ['POST'], requirements: ['locale' => self::LOCALE])]
    #[Csrf('admin-languages')]
    public function revert(string $locale, Request $request): Response
    {
        $domain = (string) $request->request->get('domain');

        if (in_array($domain, $this->catalog->domains(), true)) {
            $this->catalog->revert($domain, $locale, (string) $request->request->get('key'));
        }

        return $this->done('admin_language_translate', ['locale' => $locale, 'domain' => $domain, '_fragment' => 'entries'], 'admin.languages.entries.reverted');
    }

    #[Route('/countries', name: 'country_add', methods: ['POST'])]
    #[Csrf('admin-languages')]
    public function addCountry(Request $request, CountryRepository $countries): Response
    {
        $code = strtoupper(trim((string) $request->request->get('code')));
        $name = mb_substr(trim((string) $request->request->get('name')), 0, 60);

        if (!preg_match('/^[A-Z]{2}$/', $code) || $name === '') {
            $this->addFlash('error', 'admin.languages.countries.invalid');
        } elseif ($countries->findByCode($code) !== null) {
            $this->addFlash('error', 'admin.languages.countries.exists');
        } else {
            $countries->save((new Country())->setCode($code)->setName($name)->setLocale(TranslationCatalog::REFERENCE), true);
            $this->audit('country.save', $code);
            $this->addFlash('success', 'admin.flash.saved');
        }

        return $this->redirectToRoute('admin_language_index', ['_fragment' => 'countries']);
    }

    #[Route('/countries/{id}', name: 'country_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-languages')]
    public function updateCountry(int $id, Request $request, CountryRepository $countries): Response
    {
        $country = $countries->find($id) ?? throw $this->createNotFoundException();
        $name = mb_substr(trim((string) $request->request->get('name')), 0, 60);
        $locale = (string) $request->request->get('locale');

        $countries->save($country
            ->setName($name !== '' ? $name : $country->getName())
            ->setLocale(in_array($locale, $this->catalog->locales(), true) ? $locale : $country->getLocale()), true);
        $this->saveTranslations($country, $request);
        $this->audit('country.save', $country->getCode());

        return $this->done('admin_language_index', ['_fragment' => 'countries']);
    }

    #[Route('/countries/{id}/delete', name: 'country_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-languages')]
    public function deleteCountry(int $id, CountryRepository $countries, UserRepository $users): Response
    {
        $country = $countries->find($id) ?? throw $this->createNotFoundException();
        $users->getOrm()->getConnection()->executeStatement('UPDATE `user` SET country = NULL WHERE country = :code', ['code' => $country->getCode()]);
        $this->deleteTranslations($country);
        $countries->delete($country, true);
        $this->audit('country.delete', $country->getCode());

        return $this->done('admin_language_index', ['_fragment' => 'countries'], 'admin.flash.deleted');
    }

    #[Route('/{locale}/unlock', name: 'unlock', methods: ['POST'], requirements: ['locale' => self::LOCALE])]
    #[Csrf('admin-languages')]
    public function unlock(string $locale): Response
    {
        $this->catalog->unlock($locale);

        return $this->redirectToRoute('admin_language_index');
    }

    protected function known(TranslationManagerInterface $translator, string $locale): bool
    {
        try {
            $translator->getLanguageName($locale);
        } catch (TranslationException) {
            return false;
        }

        return true;
    }
}
