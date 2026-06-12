<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_dnaaitranslator
 */

namespace Dna\Component\DnaAiTranslator\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class TranslatorService
{
    public const VERSION = '1.0.18';
    public const OPTION = 'com_dnaaitranslator';

    private array $requiredLanguages = [
        'en-GB' => 'English',
        'de-DE' => 'Deutsch',
        'fr-FR' => 'Français',
        'es-ES' => 'Español',
    ];

    private array $langSuffix = [
        'en-GB' => 'EN',
        'de-DE' => 'DE',
        'fr-FR' => 'FR',
        'es-ES' => 'ES',
    ];

    public function getSettings(): array
    {
        // Read runtime settings through Joomla component params as required.
        $params = ComponentHelper::getParams(self::OPTION);

        return $this->settingsFromParams($params);
    }

    private function settingsFromParams(Registry $params): array
    {
        $targets = $this->normalizeTargetLanguages($params->get('target_languages', array_keys($this->requiredLanguages)));

        // Backward compatibility with v1.0.14/1.0.15 source_scope.
        $legacyScope = (string) $params->get('source_scope', 'all');
        if (!in_array($legacyScope, ['all', 'categories', 'articles'], true)) {
            $legacyScope = 'all';
        }

        $filterCategories = $params->exists('source_filter_categories_enabled')
            ? $this->normalizeBool($params->get('source_filter_categories_enabled', 0), false)
            : ($legacyScope === 'categories');

        $filterArticles = $params->exists('source_filter_articles_enabled')
            ? $this->normalizeBool($params->get('source_filter_articles_enabled', 0), false)
            : ($legacyScope === 'articles');

        return [
            'version'                           => self::VERSION,
            'mode'                              => $this->normalizeMode($params->get('mode', 'live')),
            'api_key'                           => (string) $params->get('api_key', ''),
            'model'                             => (string) $params->get('model', 'gpt-4o-mini'),
            'api_timeout'                       => max(10, min(110, (int) $params->get('api_timeout', 55))),
            'max_items'                         => $this->normalizeMaxItems($params->get('max_items', 1)),
            'target_languages'                  => $targets,
            'translate_articles'                => $this->normalizeBool($params->get('translate_articles', 1), true),
            'translate_categories'              => $this->normalizeBool($params->get('translate_categories', 1), true),
            'create_article_associations'       => $this->normalizeBool($params->get('create_article_associations', 1), true),
            'create_category_associations'      => $this->normalizeBool($params->get('create_category_associations', 1), true),
            'repair_workflow_associations'      => $this->normalizeBool($params->get('repair_workflow_associations', 1), true),
            'source_scope'                      => $legacyScope,
            'source_filter_categories_enabled'  => $filterCategories,
            'source_filter_articles_enabled'    => $filterArticles,
            'source_article_ids'                => $this->normalizeIdList($params->get('source_article_ids', [])),
            'source_category_ids'               => $this->normalizeIdList($params->get('source_category_ids', [])),
            'source_include_child_categories'   => $this->normalizeBool($params->get('source_include_child_categories', 1), true),
            'draft_state'                       => 0,
        ];
    }

    public function saveSettings(array $data): void
    {
        [$extensionId, $params] = $this->loadComponentParamsFromDatabase();
        $saved = $this->settingsFromParams($params);

        // The mode is deliberately preserved if the POST is incomplete.
        // This prevents LIVE/manuale from silently falling back to TEST.
        $mode = array_key_exists('mode', $data)
            ? $this->normalizeMode($data['mode'], $saved['mode'])
            : $saved['mode'];

        // Empty password posts must not erase an already saved API key.
        // A non-empty value always replaces the old key.
        $apiKeyInput = array_key_exists('api_key', $data) ? trim((string) $data['api_key']) : '';
        $apiKey = $apiKeyInput !== '' ? $apiKeyInput : (string) $params->get('api_key', '');

        $modelInput = array_key_exists('model', $data) ? trim((string) $data['model']) : '';
        $model = $modelInput !== '' ? $modelInput : trim((string) $params->get('model', 'gpt-4o-mini'));
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }

        $timeout = max(10, min(110, (int) ($data['api_timeout'] ?? (int) $params->get('api_timeout', 55))));
        $maxItems = $this->normalizeMaxItems($data['max_items'] ?? $params->get('max_items', 1));
        $targets = $this->normalizeTargetLanguages($data['target_languages'] ?? $params->get('target_languages', array_keys($this->requiredLanguages)));

        $translateArticles = array_key_exists('translate_articles', $data)
            ? $this->normalizeBool($data['translate_articles'], true)
            : $saved['translate_articles'];
        $translateCategories = array_key_exists('translate_categories', $data)
            ? $this->normalizeBool($data['translate_categories'], true)
            : $saved['translate_categories'];
        $createArticleAssociations = array_key_exists('create_article_associations', $data)
            ? $this->normalizeBool($data['create_article_associations'], true)
            : $saved['create_article_associations'];
        $createCategoryAssociations = array_key_exists('create_category_associations', $data)
            ? $this->normalizeBool($data['create_category_associations'], true)
            : $saved['create_category_associations'];
        $repairWorkflow = array_key_exists('repair_workflow_associations', $data)
            ? $this->normalizeBool($data['repair_workflow_associations'], true)
            : $saved['repair_workflow_associations'];

        $filterCategories = array_key_exists('source_filter_categories_enabled', $data)
            ? $this->normalizeBool($data['source_filter_categories_enabled'], false)
            : $saved['source_filter_categories_enabled'];
        $filterArticles = array_key_exists('source_filter_articles_enabled', $data)
            ? $this->normalizeBool($data['source_filter_articles_enabled'], false)
            : $saved['source_filter_articles_enabled'];

        $sourceArticleIds = array_key_exists('source_article_ids', $data)
            ? $this->normalizeIdList($data['source_article_ids'])
            : $this->normalizeIdList($params->get('source_article_ids', []));
        $sourceCategoryIds = array_key_exists('source_category_ids', $data)
            ? $this->normalizeIdList($data['source_category_ids'])
            : $this->normalizeIdList($params->get('source_category_ids', []));
        $sourceIncludeChildren = array_key_exists('source_include_child_categories', $data)
            ? $this->normalizeBool($data['source_include_child_categories'], true)
            : $this->normalizeBool($params->get('source_include_child_categories', 1), true);

        $sourceScope = 'all';
        if ($filterCategories && !$filterArticles) {
            $sourceScope = 'categories';
        } elseif ($filterArticles && !$filterCategories) {
            $sourceScope = 'articles';
        }

        $params->set('mode', $mode);
        $params->set('api_key', $apiKey);
        $params->set('model', $model);
        $params->set('api_timeout', $timeout);
        $params->set('max_items', $maxItems);
        $params->set('target_languages', $targets);
        $params->set('translate_articles', $translateArticles ? 1 : 0);
        $params->set('translate_categories', $translateCategories ? 1 : 0);
        $params->set('create_article_associations', $createArticleAssociations ? 1 : 0);
        $params->set('create_category_associations', $createCategoryAssociations ? 1 : 0);
        $params->set('repair_workflow_associations', $repairWorkflow ? 1 : 0);
        $params->set('source_scope', $sourceScope);
        $params->set('source_filter_categories_enabled', $filterCategories ? 1 : 0);
        $params->set('source_filter_articles_enabled', $filterArticles ? 1 : 0);
        $params->set('source_article_ids', $sourceArticleIds);
        $params->set('source_category_ids', $sourceCategoryIds);
        $params->set('source_include_child_categories', $sourceIncludeChildren ? 1 : 0);
        $params->set('version_backend_visible', self::VERSION);

        $this->saveComponentParams($params, $extensionId);
    }

    private function normalizeMode($value, string $default = 'live'): string
    {
        $mode = strtolower(trim((string) $value));

        if (in_array($mode, ['test', 'live'], true)) {
            return $mode;
        }

        return in_array($default, ['test', 'live'], true) ? $default : 'live';
    }

    private function normalizeMaxItems($value): int
    {
        return max(1, min(50, (int) $value));
    }

    private function normalizeTargetLanguages($targets): array
    {
        if (is_string($targets)) {
            $decoded = json_decode($targets, true);
            $targets = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $targets)));
        }

        $targets = array_values(array_intersect(array_keys($this->requiredLanguages), (array) $targets));

        return $targets ?: array_keys($this->requiredLanguages);
    }

    private function normalizeIdList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[,;\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                foreach ($this->normalizeIdList($item) as $id) {
                    $ids[] = $id;
                }
                continue;
            }

            foreach (preg_split('/[,;\s]+/', (string) $item, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function normalizeBool($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $value = strtolower(trim((string) $value));

        if (in_array($value, ['1', 'true', 'yes', 'on', 'si', 'sì'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function loadComponentParamsFromDatabase(): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote(self::OPTION));

        $row = $db->setQuery($query)->loadObject();

        if (!$row || (int) $row->extension_id < 1) {
            throw new \RuntimeException('Component extension row not found for ' . self::OPTION);
        }

        return [(int) $row->extension_id, new Registry((string) $row->params)];
    }

    private function saveComponentParams(Registry $params, ?int $extensionId = null): void
    {
        if ($extensionId === null) {
            [$extensionId, ] = $this->loadComponentParamsFromDatabase();
        }

        $db = Factory::getDbo();
        $paramsJson = $params->toString('JSON');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $paramsJson)
            ->bind(':id', $extensionId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        $this->refreshComponentHelperCache($params);
    }

    private function refreshComponentHelperCache(Registry $params): void
    {
        try {
            $component = ComponentHelper::getComponent(self::OPTION, true);

            if (is_object($component)) {
                if (method_exists($component, 'setParams')) {
                    $component->setParams($params);
                    return;
                }

                if (property_exists($component, 'params')) {
                    $component->params = $params->toString('JSON');
                }
            }
        } catch (\Throwable $e) {
            // The database row is already saved. Cache refresh is only a same-request helper.
        }
    }

    public function getLanguageStatus(): array
    {
        $db = Factory::getDbo();
        $status = [];

        foreach ($this->requiredLanguages as $tag => $label) {
            $installed = false;
            $enabled = false;
            $published = false;
            $title = $label;

            try {
                $query = $db->getQuery(true)
                    ->select([$db->quoteName('extension_id'), $db->quoteName('enabled')])
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('language'))
                    ->where($db->quoteName('element') . ' = :tag')
                    ->where($db->quoteName('client_id') . ' = 0')
                    ->bind(':tag', $tag);
                $row = $db->setQuery($query)->loadObject();
                $installed = !empty($row);
                $enabled = $installed ? ((int) $row->enabled === 1) : false;
            } catch (\Throwable $e) {
                $installed = false;
                $enabled = false;
            }

            try {
                $query = $db->getQuery(true)
                    ->select([$db->quoteName('title'), $db->quoteName('published')])
                    ->from($db->quoteName('#__languages'))
                    ->where($db->quoteName('lang_code') . ' = :tag')
                    ->bind(':tag', $tag);
                $row = $db->setQuery($query)->loadObject();

                if ($row) {
                    $published = ((int) $row->published === 1);
                    $title = (string) $row->title;
                }
            } catch (\Throwable $e) {
                $published = false;
            }

            $status[$tag] = [
                'label'     => $title ?: $label,
                'installed' => $installed,
                'enabled'   => $enabled,
                'published' => $published,
                'ok'        => $installed && $enabled && $published,
            ];
        }

        return $status;
    }

    private function assertLanguagesReady(): void
    {
        $bad = [];
        $targets = $this->getSettings()['target_languages'];

        foreach ($this->getLanguageStatus() as $tag => $row) {
            if (!in_array($tag, $targets, true)) {
                continue;
            }

            if (empty($row['ok'])) {
                $bits = [];
                if (empty($row['installed'])) {
                    $bits[] = Text::_('COM_DNAAITRANSLATOR_LANG_MISSING_INSTALLED');
                }
                if (empty($row['enabled'])) {
                    $bits[] = Text::_('COM_DNAAITRANSLATOR_LANG_MISSING_ENABLED');
                }
                if (empty($row['published'])) {
                    $bits[] = Text::_('COM_DNAAITRANSLATOR_LANG_MISSING_PUBLISHED');
                }
                $bad[] = $tag . ' (' . implode(', ', $bits) . ')';
            }
        }

        if ($bad) {
            throw new \RuntimeException(Text::sprintf('COM_DNAAITRANSLATOR_LANG_BLOCKED', implode('; ', $bad)));
        }
    }

    public function getStats(): array
    {
        $db = Factory::getDbo();
        $settings = $this->getSettings();
        $langs = $settings['target_languages'];
        $langList = implode(',', array_map([$db, 'quote'], $langs));

        $stats = [
            'sources'      => 0,
            'total_pairs'  => 0,
            'done'         => 0,
            'error'        => 0,
            'pending'      => 0,
            'asset_zero_articles' => 0,
            'asset_zero_categories' => 0,
            'missing_workflows' => 0,
        ];

        try {
            $query = $this->getSourceArticlesQuery('COUNT(*)');
            $stats['sources'] = (int) $db->setQuery($query)->loadResult();
            $stats['total_pairs'] = $stats['sources'] * count($langs);
        } catch (\Throwable $e) {
        }

        try {
            $query = $db->getQuery(true)
                ->select('m.status, COUNT(*) AS c')
                ->from($db->quoteName('#__dnaaitranslator_map', 'm'))
                ->innerJoin($db->quoteName('#__content', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('m.source_article_id'))
                ->where($db->quoteName('c.language') . ' IN (' . $db->quote('*') . ',' . $db->quote('it-IT') . ')')
                ->where($db->quoteName('c.state') . ' IN (0,1)')
                ->where($db->quoteName('m.target_language') . ' IN (' . $langList . ')')
                ->group($db->quoteName('m.status'));
            $this->applySourceSelection($query, $settings, 'c');

            foreach ((array) $db->setQuery($query)->loadObjectList() as $row) {
                $stats[(string) $row->status] = (int) $row->c;
            }
        } catch (\Throwable $e) {
        }

        $stats['pending'] = max(0, $stats['total_pairs'] - (int) ($stats['done'] ?? 0));

        if ($langs) {
            try {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('language') . ' IN (' . $langList . ')')
                    ->where($db->quoteName('asset_id') . ' = 0');
                $stats['asset_zero_articles'] = (int) $db->setQuery($query)->loadResult();
            } catch (\Throwable $e) {
            }

            try {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                    ->where($db->quoteName('language') . ' IN (' . $langList . ')')
                    ->where($db->quoteName('asset_id') . ' = 0');
                $stats['asset_zero_categories'] = (int) $db->setQuery($query)->loadResult();
            } catch (\Throwable $e) {
            }

            try {
                if ($this->tableExists('#__workflow_associations')) {
                    $query = $db->getQuery(true)
                        ->select('COUNT(c.id)')
                        ->from($db->quoteName('#__content', 'c'))
                        ->leftJoin($db->quoteName('#__workflow_associations', 'wa') . ' ON wa.item_id = c.id AND wa.extension = ' . $db->quote('com_content.article'))
                        ->where($db->quoteName('c.language') . ' IN (' . $langList . ')')
                        ->where($db->quoteName('c.state') . ' IN (0,1)')
                        ->where($db->quoteName('wa.item_id') . ' IS NULL');
                    $stats['missing_workflows'] = (int) $db->setQuery($query)->loadResult();
                }
            } catch (\Throwable $e) {
            }
        }

        return $stats;
    }

    public function peekNextPair(): ?array
    {
        try {
            return $this->findNextPair(false);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getRunChecks(): array
    {
        $settings = $this->getSettings();
        $checks = [];
        $sourceCount = 0;
        $categoryCount = 0;

        try {
            $db = Factory::getDbo();
            $sourceCount = (int) $db->setQuery($this->getSourceArticlesQuery('COUNT(*)'))->loadResult();
        } catch (\Throwable $e) {
            $checks[] = [
                'level' => 'danger',
                'label' => Text::_('COM_DNAAITRANSLATOR_CHECK_ARTICLES'),
                'text'  => Text::sprintf('COM_DNAAITRANSLATOR_CHECK_DB_ERROR', $e->getMessage()),
            ];
        }

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('published') . ' IN (0,1)')
                ->where($db->quoteName('id') . ' > 1');
            $categoryCount = (int) $db->setQuery($query)->loadResult();
        } catch (\Throwable $e) {
            $checks[] = [
                'level' => 'warning',
                'label' => Text::_('COM_DNAAITRANSLATOR_CHECK_CATEGORIES'),
                'text'  => Text::sprintf('COM_DNAAITRANSLATOR_CHECK_DB_ERROR', $e->getMessage()),
            ];
        }

        $checks[] = [
            'level' => trim((string) $settings['api_key']) !== '' ? 'success' : ($settings['mode'] === 'live' ? 'danger' : 'warning'),
            'label' => Text::_('COM_DNAAITRANSLATOR_CHECK_API_KEY'),
            'text'  => trim((string) $settings['api_key']) !== ''
                ? Text::_('COM_DNAAITRANSLATOR_CHECK_API_KEY_OK')
                : Text::_('COM_DNAAITRANSLATOR_CHECK_API_KEY_MISSING'),
        ];

        $badLanguages = [];
        foreach ($this->getLanguageStatus() as $tag => $row) {
            if (!in_array($tag, $settings['target_languages'], true)) {
                continue;
            }

            if (empty($row['ok'])) {
                $badLanguages[] = $tag;
            }
        }

        $checks[] = [
            'level' => $badLanguages ? 'danger' : 'success',
            'label' => Text::_('COM_DNAAITRANSLATOR_CHECK_LANGUAGES'),
            'text'  => $badLanguages
                ? Text::sprintf('COM_DNAAITRANSLATOR_CHECK_LANGUAGES_BAD', implode(', ', $badLanguages))
                : Text::_('COM_DNAAITRANSLATOR_CHECK_LANGUAGES_OK'),
        ];

        $checks[] = [
            'level' => !empty($settings['translate_articles']) && $sourceCount > 0 ? 'success' : 'danger',
            'label' => Text::_('COM_DNAAITRANSLATOR_CHECK_ARTICLES'),
            'text'  => !empty($settings['translate_articles'])
                ? Text::sprintf('COM_DNAAITRANSLATOR_CHECK_ARTICLES_COUNT', $sourceCount)
                : Text::_('COM_DNAAITRANSLATOR_CHECK_ARTICLES_DISABLED'),
        ];

        $checks[] = [
            'level' => $categoryCount > 0 ? 'success' : 'warning',
            'label' => Text::_('COM_DNAAITRANSLATOR_CHECK_CATEGORIES'),
            'text'  => Text::sprintf('COM_DNAAITRANSLATOR_CHECK_CATEGORIES_COUNT', $categoryCount),
        ];

        if (!empty($settings['source_filter_categories_enabled']) && empty($settings['source_category_ids'])) {
            $checks[] = [
                'level' => 'danger',
                'label' => Text::_('COM_DNAAITRANSLATOR_SOURCE_FILTER_CATEGORIES'),
                'text'  => Text::_('COM_DNAAITRANSLATOR_CHECK_CATEGORY_FILTER_EMPTY'),
            ];
        }

        if (!empty($settings['source_filter_articles_enabled']) && empty($settings['source_article_ids'])) {
            $checks[] = [
                'level' => 'danger',
                'label' => Text::_('COM_DNAAITRANSLATOR_SOURCE_FILTER_ARTICLES'),
                'text'  => Text::_('COM_DNAAITRANSLATOR_CHECK_ARTICLE_FILTER_EMPTY'),
            ];
        }

        return $checks;
    }

    public function getRecentLog(int $limit = 20): array
    {
        try {
            $db = Factory::getDbo();

            if (!$this->tableExists('#__dnaaitranslator_map')) {
                return [];
            }

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('m.id'),
                    $db->quoteName('m.source_article_id'),
                    $db->quoteName('m.target_language'),
                    $db->quoteName('m.target_article_id'),
                    $db->quoteName('m.status'),
                    $db->quoteName('m.notes'),
                    $db->quoteName('m.created'),
                    $db->quoteName('m.updated'),
                    $db->quoteName('s.title', 'source_title'),
                    $db->quoteName('t.title', 'target_title'),
                ])
                ->from($db->quoteName('#__dnaaitranslator_map', 'm'))
                ->leftJoin($db->quoteName('#__content', 's') . ' ON ' . $db->quoteName('s.id') . ' = ' . $db->quoteName('m.source_article_id'))
                ->leftJoin($db->quoteName('#__content', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('m.target_article_id'))
                ->order($db->quoteName('m.updated') . ' DESC, ' . $db->quoteName('m.id') . ' DESC');

            return (array) $db->setQuery($query, 0, max(1, min(100, $limit)))->loadAssocList();
        } catch (\Throwable $e) {
            return [[
                'source_article_id' => 0,
                'target_language' => '',
                'target_article_id' => 0,
                'status' => 'error',
                'notes' => Text::sprintf('COM_DNAAITRANSLATOR_CHECK_DB_ERROR', $e->getMessage()),
                'created' => '',
                'updated' => '',
                'source_title' => '',
                'target_title' => '',
            ]];
        }
    }

    public function startTranslation(): array
    {
        $settings = $this->getSettings();
        $maxItems = $this->normalizeMaxItems($settings['max_items'] ?? 1);
        $messages = [];
        $last = ['done' => false, 'message' => ''];

        for ($i = 0; $i < $maxItems; $i++) {
            $last = $this->translateNext();

            if (!empty($last['message'])) {
                $messages[] = (count($messages) + 1) . ') ' . $last['message'];
            }

            if (!empty($last['blocked']) || !empty($last['done']) || $settings['mode'] === 'test') {
                break;
            }
        }

        $last['message'] = $messages ? implode(' | ', $messages) : ($last['message'] ?? Text::_('COM_DNAAITRANSLATOR_TRANSLATION_STEP_DONE'));

        return $last;
    }

    public function translateNext(): array
    {
        $settings = $this->getSettings();
        $blocking = $this->getBlockingReasons($settings);

        if ($blocking) {
            return [
                'done' => false,
                'blocked' => true,
                'message' => Text::sprintf('COM_DNAAITRANSLATOR_RUN_BLOCKED', implode(' ', $blocking)),
            ];
        }

        $this->assertLanguagesReady();

        $pair = $this->findNextPair(true);

        if (!$pair) {
            return [
                'done' => true,
                'message' => Text::_('COM_DNAAITRANSLATOR_ALL_DONE'),
            ];
        }

        if ($settings['mode'] === 'test') {
            return [
                'done' => false,
                'message' => Text::sprintf(
                    'COM_DNAAITRANSLATOR_TEST_MODE_NO_CREATE',
                    $pair['source']->id,
                    $pair['source']->title,
                    $pair['target_language']
                ),
            ];
        }

        $source = $pair['source'];
        $lang = $pair['target_language'];

        $this->upsertMap((int) $source->id, $lang, 0, 'running', Text::_('COM_DNAAITRANSLATOR_RUNNING'));

        try {
            $sourceCategory = $this->getCategory((int) $source->catid);
            $translateCategories = !empty($settings['translate_categories']);
            $needsCategory = $translateCategories && !$this->findTranslatedCategoryId((int) $source->catid, $lang);

            $translated = $this->callOpenAi($settings, $source, $sourceCategory, $lang, $needsCategory);
            $categoryId = $translateCategories
                ? $this->ensureTranslatedCategory($sourceCategory, $lang, $translated['category_title'] ?? null)
                : (int) $source->catid;
            $articleId = $this->createTranslatedArticle($source, $translated, $categoryId, $lang);

            if (!empty($settings['repair_workflow_associations'])) {
                $this->ensureWorkflowAssociation($articleId, (int) $source->id);
            }

            if (!empty($settings['create_article_associations'])) {
                $this->ensureLanguageAssociation((int) $source->id, $articleId, $lang);
            }

            $this->upsertMap((int) $source->id, $lang, $articleId, 'done', Text::sprintf('COM_DNAAITRANSLATOR_CREATED_ONE', $source->id, $source->title, $lang, $articleId));

            return [
                'done' => false,
                'message' => Text::sprintf(
                    'COM_DNAAITRANSLATOR_CREATED_ONE',
                    $source->id,
                    $source->title,
                    $lang,
                    $articleId
                ),
            ];
        } catch (\Throwable $e) {
            $this->upsertMap((int) $source->id, $lang, 0, 'error', $e->getMessage());
            throw new \RuntimeException(Text::sprintf('COM_DNAAITRANSLATOR_STOPPED_API_ERROR', $e->getMessage()), 0, $e);
        }
    }

    private function getBlockingReasons(array $settings): array
    {
        $reasons = [];

        if (empty($settings['translate_articles'])) {
            $reasons[] = Text::_('COM_DNAAITRANSLATOR_CHECK_ARTICLES_DISABLED');
        }

        if (empty($settings['target_languages'])) {
            $reasons[] = Text::_('COM_DNAAITRANSLATOR_CHECK_LANGUAGES_EMPTY');
        }

        if ($settings['mode'] === 'live' && trim((string) $settings['api_key']) === '') {
            $reasons[] = Text::_('COM_DNAAITRANSLATOR_API_KEY_MISSING');
        }

        if (!empty($settings['source_filter_categories_enabled']) && empty($settings['source_category_ids'])) {
            $reasons[] = Text::_('COM_DNAAITRANSLATOR_CHECK_CATEGORY_FILTER_EMPTY');
        }

        if (!empty($settings['source_filter_articles_enabled']) && empty($settings['source_article_ids'])) {
            $reasons[] = Text::_('COM_DNAAITRANSLATOR_CHECK_ARTICLE_FILTER_EMPTY');
        }

        try {
            $db = Factory::getDbo();
            $sourceCount = (int) $db->setQuery($this->getSourceArticlesQuery('COUNT(*)'))->loadResult();

            if ($sourceCount < 1) {
                $reasons[] = Text::_('COM_DNAAITRANSLATOR_CHECK_NO_SOURCE_ARTICLES');
            }
        } catch (\Throwable $e) {
            $reasons[] = Text::sprintf('COM_DNAAITRANSLATOR_CHECK_DB_ERROR', $e->getMessage());
        }

        foreach ($this->getLanguageStatus() as $tag => $row) {
            if (!in_array($tag, $settings['target_languages'], true)) {
                continue;
            }

            if (empty($row['ok'])) {
                $reasons[] = Text::sprintf('COM_DNAAITRANSLATOR_CHECK_LANGUAGE_ONE_BAD', $tag);
            }
        }

        return $reasons;
    }

    public function repairIncomplete(): array
    {
        // v1.0.18: repair must remain available even if one or more target languages
        // are missing/unpublished. The mandatory language check is intentionally
        // kept only inside translateNext(), so it blocks Avvia/Continua traduzione
        // but does not block Ripara contenuti tradotti incompleti.
        $articleAssets = 0;
        $categoryAssets = 0;
        $workflow = 0;
        $mapFixed = 0;

        $db = Factory::getDbo();
        $settings = $this->getSettings();
        $langs = $settings['target_languages'];
        $langList = implode(',', array_map([$db, 'quote'], $langs));

        if ($langs) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('language') . ' IN (' . $langList . ')')
                ->where($db->quoteName('asset_id') . ' = 0');

            foreach ((array) $db->setQuery($query)->loadColumn() as $id) {
                if ($this->repairArticleAsset((int) $id)) {
                    $articleAssets++;
                }
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('language') . ' IN (' . $langList . ')')
                ->where($db->quoteName('asset_id') . ' = 0');

            foreach ((array) $db->setQuery($query)->loadColumn() as $id) {
                if ($this->repairCategoryAsset((int) $id)) {
                    $categoryAssets++;
                }
            }

            if (!empty($settings['repair_workflow_associations']) && $this->tableExists('#__workflow_associations')) {
                $query = $db->getQuery(true)
                    ->select([$db->quoteName('c.id'), $db->quoteName('m.source_article_id')])
                    ->from($db->quoteName('#__content', 'c'))
                    ->leftJoin($db->quoteName('#__workflow_associations', 'wa') . ' ON wa.item_id = c.id AND wa.extension = ' . $db->quote('com_content.article'))
                    ->leftJoin($db->quoteName('#__dnaaitranslator_map', 'm') . ' ON m.target_article_id = c.id')
                    ->where($db->quoteName('c.language') . ' IN (' . $langList . ')')
                    ->where($db->quoteName('c.state') . ' IN (0,1)')
                    ->where($db->quoteName('wa.item_id') . ' IS NULL');

                foreach ((array) $db->setQuery($query)->loadObjectList() as $row) {
                    if ($this->ensureWorkflowAssociation((int) $row->id, (int) $row->source_article_id)) {
                        $workflow++;
                    }
                }
            }
        }

        $mapFixed += $this->adoptExistingTargetsIntoMap();

        return [
            'message' => Text::sprintf('COM_DNAAITRANSLATOR_REPAIR_RESULT', $articleAssets, $categoryAssets, $workflow, $mapFixed),
        ];
    }

    public function resetState(): void
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->delete($db->quoteName('#__dnaaitranslator_map'));
        $db->setQuery($query)->execute();
    }

    private function findNextPair(bool $adoptExisting): ?array
    {
        if ($adoptExisting) {
            $this->adoptExistingTargetsIntoMap();
        }

        $db = Factory::getDbo();
        $langs = $this->getSettings()['target_languages'];

        $query = $this->getSourceArticlesQuery('*')
            ->order($db->quoteName('id') . ' ASC');
        $sources = (array) $db->setQuery($query)->loadObjectList();

        foreach ($sources as $source) {
            foreach ($langs as $lang) {
                if ($this->isPairDone((int) $source->id, $lang)) {
                    continue;
                }

                return [
                    'source' => $source,
                    'target_language' => $lang,
                ];
            }
        }

        return null;
    }

    private function getSourceArticlesQuery(string $select)
    {
        $db = Factory::getDbo();
        $settings = $this->getSettings();
        $query = $db->getQuery(true)
            ->select($select)
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('language') . ' IN (' . $db->quote('*') . ',' . $db->quote('it-IT') . ')')
            ->where($db->quoteName('state') . ' IN (0,1)');

        if (empty($settings['translate_articles'])) {
            $query->where('0 = 1');
            return $query;
        }

        return $this->applySourceSelection($query, $settings);
    }

    private function applySourceSelection($query, array $settings, string $alias = '')
    {
        $db = Factory::getDbo();
        $idColumn = $alias !== '' ? $db->quoteName($alias . '.id') : $db->quoteName('id');
        $catColumn = $alias !== '' ? $db->quoteName($alias . '.catid') : $db->quoteName('catid');

        $conditions = [];

        if (!empty($settings['source_filter_articles_enabled'])) {
            $ids = $this->normalizeIdList($settings['source_article_ids'] ?? []);
            $conditions[] = $ids
                ? $idColumn . ' IN (' . implode(',', array_map('intval', $ids)) . ')'
                : '0 = 1';
        }

        if (!empty($settings['source_filter_categories_enabled'])) {
            $ids = $this->getEffectiveCategoryIds(
                $this->normalizeIdList($settings['source_category_ids'] ?? []),
                !empty($settings['source_include_child_categories'])
            );
            $conditions[] = $ids
                ? $catColumn . ' IN (' . implode(',', array_map('intval', $ids)) . ')'
                : '0 = 1';
        }

        if ($conditions) {
            $query->where('(' . implode(' OR ', $conditions) . ')');
        }

        return $query;
    }

    private function getEffectiveCategoryIds(array $categoryIds, bool $includeChildren): array
    {
        $categoryIds = $this->normalizeIdList($categoryIds);

        if (!$categoryIds || !$includeChildren) {
            return $categoryIds;
        }

        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('lft'), $db->quoteName('rgt')])
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $categoryIds)) . ')');
            $rows = (array) $db->setQuery($query)->loadObjectList();

            if (!$rows) {
                return $categoryIds;
            }

            $conditions = [];
            foreach ($rows as $row) {
                $conditions[] = '(' . $db->quoteName('lft') . ' >= ' . (int) $row->lft . ' AND ' . $db->quoteName('rgt') . ' <= ' . (int) $row->rgt . ')';
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where('(' . implode(' OR ', $conditions) . ')');
            $categoryIds = array_merge($categoryIds, array_map('intval', (array) $db->setQuery($query)->loadColumn()));
        } catch (\Throwable $e) {
        }

        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        sort($categoryIds, SORT_NUMERIC);

        return $categoryIds;
    }

    private function isPairDone(int $sourceId, string $lang): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('target_article_id'), $db->quoteName('status')])
            ->from($db->quoteName('#__dnaaitranslator_map'))
            ->where($db->quoteName('source_article_id') . ' = :source')
            ->where($db->quoteName('target_language') . ' = :lang')
            ->bind(':source', $sourceId, ParameterType::INTEGER)
            ->bind(':lang', $lang);
        $row = $db->setQuery($query)->loadObject();

        if (!$row || (string) $row->status !== 'done' || (int) $row->target_article_id < 1) {
            return false;
        }

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $row->target_article_id, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    private function upsertMap(int $sourceId, string $lang, int $targetId, string $status, string $notes): void
    {
        $db = Factory::getDbo();
        $now = Factory::getDate()->toSql();
        $notes = mb_substr($notes, 0, 1000);

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__dnaaitranslator_map'))
            ->where($db->quoteName('source_article_id') . ' = :source')
            ->where($db->quoteName('target_language') . ' = :lang')
            ->bind(':source', $sourceId, ParameterType::INTEGER)
            ->bind(':lang', $lang);
        $id = (int) $db->setQuery($query)->loadResult();

        if ($id) {
            $fields = [
                $db->quoteName('status') . ' = :status',
                $db->quoteName('notes') . ' = :notes',
                $db->quoteName('updated') . ' = :updated',
            ];

            if ($targetId > 0) {
                $fields[] = $db->quoteName('target_article_id') . ' = :target';
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__dnaaitranslator_map'))
                ->set($fields)
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':status', $status)
                ->bind(':notes', $notes)
                ->bind(':updated', $now)
                ->bind(':id', $id, ParameterType::INTEGER);

            if ($targetId > 0) {
                $query->bind(':target', $targetId, ParameterType::INTEGER);
            }

            $db->setQuery($query)->execute();
            return;
        }

        $columns = ['source_article_id', 'target_language', 'target_article_id', 'status', 'notes', 'created', 'updated'];
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__dnaaitranslator_map'))
            ->columns($db->quoteName($columns))
            ->values(':source, :lang, :target, :status, :notes, :created, :updated')
            ->bind(':source', $sourceId, ParameterType::INTEGER)
            ->bind(':lang', $lang)
            ->bind(':target', $targetId, ParameterType::INTEGER)
            ->bind(':status', $status)
            ->bind(':notes', $notes)
            ->bind(':created', $now)
            ->bind(':updated', $now);
        $db->setQuery($query)->execute();
    }

    private function callOpenAi(array $settings, object $article, ?object $category, string $targetLang, bool $includeCategory): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException(Text::_('COM_DNAAITRANSLATOR_CURL_MISSING'));
        }

        $payload = [
            'model' => $settings['model'],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You translate Joomla website content from Italian to the requested target language. Preserve HTML tags, URLs, image paths, shortcodes, Joomla read-more markers, numbers, names, and diving/location terminology. Return only valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'target_language_code' => $targetLang,
                        'target_language_name' => $this->requiredLanguages[$targetLang] ?? $targetLang,
                        'return_json_schema' => [
                            'title' => 'translated article title without language suffixes like [EN]',
                            'introtext' => 'translated intro HTML',
                            'fulltext' => 'translated full HTML',
                            'metadesc' => 'translated meta description',
                            'metakey' => 'translated meta keywords',
                            'category_title' => $includeCategory ? 'translated category title without suffixes' : null,
                        ],
                        'source' => [
                            'title' => $this->stripLanguageSuffix((string) $article->title),
                            'introtext' => (string) $article->introtext,
                            'fulltext' => (string) $article->fulltext,
                            'metadesc' => (string) $article->metadesc,
                            'metakey' => (string) $article->metakey,
                            'category_title' => $includeCategory && $category ? $this->stripLanguageSuffix((string) $category->title) : null,
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $settings['api_key'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $settings['api_timeout'],
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException(Text::sprintf('COM_DNAAITRANSLATOR_CURL_ERROR', $error ?: $errno));
        }

        $decoded = json_decode((string) $body, true);

        if ($http < 200 || $http >= 300) {
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $http);
            throw new \RuntimeException(Text::sprintf('COM_DNAAITRANSLATOR_OPENAI_HTTP_ERROR', $msg));
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $json = json_decode((string) $content, true);

        if (!is_array($json)) {
            throw new \RuntimeException(Text::_('COM_DNAAITRANSLATOR_OPENAI_JSON_ERROR'));
        }

        $title = trim((string) ($json['title'] ?? ''));

        if ($title === '') {
            throw new \RuntimeException(Text::_('COM_DNAAITRANSLATOR_OPENAI_EMPTY_TITLE'));
        }

        return [
            'title' => $this->stripLanguageSuffix($title),
            'introtext' => (string) ($json['introtext'] ?? ''),
            'fulltext' => (string) ($json['fulltext'] ?? ''),
            'metadesc' => (string) ($json['metadesc'] ?? ''),
            'metakey' => (string) ($json['metakey'] ?? ''),
            'category_title' => isset($json['category_title']) ? $this->stripLanguageSuffix((string) $json['category_title']) : null,
        ];
    }

    private function createTranslatedArticle(object $source, array $translated, int $categoryId, string $lang): int
    {
        $app = Factory::getApplication();
        $component = $app->bootComponent('com_content');
        $factory = $component->getMVCFactory();
        $model = $factory->createModel('Article', 'Administrator', ['ignore_request' => true]);

        $images = $this->decodeJsonField($source->images ?? '');
        $urls = $this->decodeJsonField($source->urls ?? '');
        $attribs = $this->decodeJsonField($source->attribs ?? '');
        $metadata = $this->decodeJsonField($source->metadata ?? '');
        $metadata['robots'] = $metadata['robots'] ?? '';
        $metadata['author'] = $metadata['author'] ?? '';
        $metadata['rights'] = $metadata['rights'] ?? '';

        $articleText = (string) $translated['introtext'];
        if (trim((string) $translated['fulltext']) !== '') {
            $articleText .= '<hr id="system-readmore">' . (string) $translated['fulltext'];
        }

        $title = $this->stripLanguageSuffix((string) $translated['title']);
        $alias = $this->uniqueAlias('#__content', $title, $categoryId, $lang);

        $data = [
            'id' => 0,
            'title' => $title,
            'alias' => $alias,
            'note' => 'DNA AI Translator ' . self::VERSION . ' - source ID ' . (int) $source->id,
            'articletext' => $articleText,
            'introtext' => (string) $translated['introtext'],
            'fulltext' => (string) $translated['fulltext'],
            'catid' => $categoryId,
            'state' => 0,
            'access' => (int) ($source->access ?? 1),
            'language' => $lang,
            'created' => '',
            'created_by' => (int) $app->getIdentity()->id,
            'created_by_alias' => '',
            'modified' => '',
            'publish_up' => '',
            'publish_down' => '',
            'featured' => 0,
            'metakey' => (string) ($translated['metakey'] ?? ''),
            'metadesc' => (string) ($translated['metadesc'] ?? ''),
            // Do not write xreference: some Joomla 6 content tables do not have this column.
            // The component keeps source/target links in #__dnaaitranslator_map.
            'attribs' => $attribs,
            'images' => $images,
            'urls' => $urls,
            'metadata' => $metadata,
            'transition' => '',
            'tags' => [],
        ];

        if (!$model->save($data)) {
            throw new \RuntimeException($model->getError() ?: Text::_('COM_DNAAITRANSLATOR_ARTICLE_SAVE_FAILED'));
        }

        $id = (int) $model->getState($model->getName() . '.id');
        if (!$id) {
            $id = $this->findArticleByXreference((int) $source->id, $lang);
        }

        if (!$id) {
            throw new \RuntimeException(Text::_('COM_DNAAITRANSLATOR_ARTICLE_ID_MISSING'));
        }

        return $id;
    }

    private function ensureTranslatedCategory(?object $sourceCategory, string $lang, ?string $translatedTitle): int
    {
        if (!$sourceCategory || (int) $sourceCategory->id < 2) {
            return 2;
        }

        $existing = $this->findTranslatedCategoryId((int) $sourceCategory->id, $lang);
        if ($existing) {
            if (!empty($this->getSettings()['create_category_associations'])) {
                $this->ensureCategoryLanguageAssociation((int) $sourceCategory->id, $existing, $lang);
            }
            return $existing;
        }

        $app = Factory::getApplication();
        $component = $app->bootComponent('com_categories');
        $factory = $component->getMVCFactory();
        $model = $factory->createModel('Category', 'Administrator', ['ignore_request' => true]);

        $title = trim((string) $translatedTitle);
        if ($title === '') {
            $title = $this->stripLanguageSuffix((string) $sourceCategory->title);
        }

        $title = $this->stripLanguageSuffix($title);
        $alias = $this->uniqueAlias('#__categories', $title, 1, $lang, 'com_content');
        $params = $this->decodeJsonField($sourceCategory->params ?? '');
        $metadata = $this->decodeJsonField($sourceCategory->metadata ?? '');

        $data = [
            'id' => 0,
            'parent_id' => 1,
            'extension' => 'com_content',
            'title' => $title,
            'alias' => $alias,
            'description' => (string) ($sourceCategory->description ?? ''),
            'published' => 0,
            'access' => (int) ($sourceCategory->access ?? 1),
            'language' => $lang,
            'params' => $params,
            'metadata' => $metadata,
            'metadesc' => '',
            'metakey' => '',
            'rules' => [],
        ];

        if (!$model->save($data)) {
            throw new \RuntimeException($model->getError() ?: Text::_('COM_DNAAITRANSLATOR_CATEGORY_SAVE_FAILED'));
        }

        $id = (int) $model->getState($model->getName() . '.id');
        if (!$id) {
            $id = $this->findTranslatedCategoryId((int) $sourceCategory->id, $lang, $alias);
        }

        if (!$id) {
            throw new \RuntimeException(Text::_('COM_DNAAITRANSLATOR_CATEGORY_ID_MISSING'));
        }

        $this->upsertCategoryMap((int) $sourceCategory->id, $lang, $id);
        $this->repairCategoryAsset($id);

        if (!empty($this->getSettings()['create_category_associations'])) {
            $this->ensureCategoryLanguageAssociation((int) $sourceCategory->id, $id, $lang);
        }

        return $id;
    }

    private function getCategory(int $id): ?object
    {
        if ($id < 1) {
            return null;
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    private function findTranslatedCategoryId(int $sourceCategoryId, string $lang, ?string $alias = null): int
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('target_category_id'))
            ->from($db->quoteName('#__dnaaitranslator_category_map'))
            ->where($db->quoteName('source_category_id') . ' = :source')
            ->where($db->quoteName('target_language') . ' = :lang')
            ->bind(':source', $sourceCategoryId, ParameterType::INTEGER)
            ->bind(':lang', $lang);
        $id = (int) $db->setQuery($query)->loadResult();

        if ($id > 0) {
            return $id;
        }

        if ($alias) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('language') . ' = :lang')
                ->where($db->quoteName('alias') . ' = :alias')
                ->bind(':lang', $lang)
                ->bind(':alias', $alias);
            $id = (int) $db->setQuery($query)->loadResult();

            if ($id > 0) {
                $this->upsertCategoryMap($sourceCategoryId, $lang, $id);
                return $id;
            }
        }

        return 0;
    }

    private function upsertCategoryMap(int $sourceCategoryId, string $lang, int $targetCategoryId): void
    {
        $db = Factory::getDbo();
        $now = Factory::getDate()->toSql();

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__dnaaitranslator_category_map'))
            ->where($db->quoteName('source_category_id') . ' = :source')
            ->where($db->quoteName('target_language') . ' = :lang')
            ->bind(':source', $sourceCategoryId, ParameterType::INTEGER)
            ->bind(':lang', $lang);
        $id = (int) $db->setQuery($query)->loadResult();

        if ($id) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__dnaaitranslator_category_map'))
                ->set($db->quoteName('target_category_id') . ' = :target')
                ->set($db->quoteName('updated') . ' = :updated')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':target', $targetCategoryId, ParameterType::INTEGER)
                ->bind(':updated', $now)
                ->bind(':id', $id, ParameterType::INTEGER);
            $db->setQuery($query)->execute();
            return;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__dnaaitranslator_category_map'))
            ->columns($db->quoteName(['source_category_id', 'target_language', 'target_category_id', 'created', 'updated']))
            ->values(':source, :lang, :target, :created, :updated')
            ->bind(':source', $sourceCategoryId, ParameterType::INTEGER)
            ->bind(':lang', $lang)
            ->bind(':target', $targetCategoryId, ParameterType::INTEGER)
            ->bind(':created', $now)
            ->bind(':updated', $now);
        $db->setQuery($query)->execute();
    }

    private function repairArticleAsset(int $id): bool
    {
        try {
            $component = Factory::getApplication()->bootComponent('com_content');
            $table = $component->getMVCFactory()->createTable('Article', 'Administrator');
            if (!$table || !$table->load($id)) {
                return false;
            }
            $table->check();
            return (bool) $table->store();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function repairCategoryAsset(int $id): bool
    {
        try {
            $component = Factory::getApplication()->bootComponent('com_categories');
            $table = $component->getMVCFactory()->createTable('Category', 'Administrator');
            if (!$table || !$table->load($id)) {
                return false;
            }
            $table->check();
            return (bool) $table->store();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureWorkflowAssociation(int $articleId, int $sourceArticleId = 0): bool
    {
        if ($articleId < 1 || !$this->tableExists('#__workflow_associations')) {
            return false;
        }

        $db = Factory::getDbo();
        $extension = 'com_content.article';

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__workflow_associations'))
            ->where($db->quoteName('item_id') . ' = :item')
            ->where($db->quoteName('extension') . ' = :extension')
            ->bind(':item', $articleId, ParameterType::INTEGER)
            ->bind(':extension', $extension);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            return false;
        }

        $stageId = 0;

        if ($sourceArticleId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('stage_id'))
                ->from($db->quoteName('#__workflow_associations'))
                ->where($db->quoteName('item_id') . ' = :source')
                ->where($db->quoteName('extension') . ' = :extension')
                ->bind(':source', $sourceArticleId, ParameterType::INTEGER)
                ->bind(':extension', $extension);
            $stageId = (int) $db->setQuery($query)->loadResult();
        }

        if ($stageId < 1) {
            $stageId = $this->getDefaultWorkflowStageId();
        }

        if ($stageId < 1) {
            return false;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__workflow_associations'))
            ->columns($db->quoteName(['item_id', 'stage_id', 'extension']))
            ->values(':item, :stage, :extension')
            ->bind(':item', $articleId, ParameterType::INTEGER)
            ->bind(':stage', $stageId, ParameterType::INTEGER)
            ->bind(':extension', $extension);
        $db->setQuery($query)->execute();

        return true;
    }

    private function getDefaultWorkflowStageId(): int
    {
        if (!$this->tableExists('#__workflow_stages') || !$this->tableExists('#__workflows')) {
            return 0;
        }

        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('s.id'))
                ->from($db->quoteName('#__workflow_stages', 's'))
                ->innerJoin($db->quoteName('#__workflows', 'w') . ' ON w.id = s.workflow_id')
                ->where($db->quoteName('w.extension') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName('s.default') . ' = 1')
                ->order($db->quoteName('w.default') . ' DESC, ' . $db->quoteName('s.id') . ' ASC');
            return (int) $db->setQuery($query, 0, 1)->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function ensureLanguageAssociation(int $sourceId, int $targetId, string $targetLang): void
    {
        if ($sourceId < 1 || $targetId < 1 || !$this->tableExists('#__associations')) {
            return;
        }

        $source = $this->getArticle($sourceId);
        if (!$source || (string) $source->language === '*') {
            return;
        }

        $db = Factory::getDbo();
        $context = 'com_content.item';
        $key = '';

        $query = $db->getQuery(true)
            ->select($db->quoteName('key'))
            ->from($db->quoteName('#__associations'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':id', $sourceId, ParameterType::INTEGER)
            ->bind(':context', $context);
        $key = (string) $db->setQuery($query)->loadResult();

        if ($key === '') {
            $key = md5('dnaaitranslator:' . $sourceId . ':' . microtime(true));
            $this->insertAssociation($sourceId, $context, $key);
        }

        $this->insertAssociation($targetId, $context, $key);
    }

    private function ensureCategoryLanguageAssociation(int $sourceId, int $targetId, string $targetLang): void
    {
        if ($sourceId < 1 || $targetId < 1 || !$this->tableExists('#__associations')) {
            return;
        }

        $source = $this->getCategory($sourceId);
        if (!$source || (string) $source->language === '*') {
            return;
        }

        $db = Factory::getDbo();
        $context = 'com_categories.item';

        $query = $db->getQuery(true)
            ->select($db->quoteName('key'))
            ->from($db->quoteName('#__associations'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':id', $sourceId, ParameterType::INTEGER)
            ->bind(':context', $context);
        $key = (string) $db->setQuery($query)->loadResult();

        if ($key === '') {
            $key = md5('dnaaitranslator:category:' . $sourceId . ':' . microtime(true));
            $this->insertAssociation($sourceId, $context, $key);
        }

        $this->insertAssociation($targetId, $context, $key);
    }

    private function insertAssociation(int $id, string $context, string $key): void
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__associations'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->bind(':context', $context);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            return;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__associations'))
            ->columns($db->quoteName(['id', 'context', 'key']))
            ->values(':id, :context, :key')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->bind(':context', $context)
            ->bind(':key', $key);
        $db->setQuery($query)->execute();
    }

    private function adoptExistingTargetsIntoMap(): int
    {
        $db = Factory::getDbo();
        $settings = $this->getSettings();
        $count = 0;

        $query = $this->getSourceArticlesQuery('*')
            ->order($db->quoteName('id') . ' ASC');
        $sources = (array) $db->setQuery($query)->loadObjectList();

        foreach ($sources as $source) {
            foreach ($settings['target_languages'] as $lang) {
                if ($this->isPairDone((int) $source->id, $lang)) {
                    continue;
                }

                $targetId = $this->findArticleByXreference((int) $source->id, $lang);

                if (!$targetId) {
                    $targetId = $this->findOldSuffixedArticle((string) $source->title, (int) $source->catid, $lang);
                }

                if ($targetId) {
                    $this->upsertMap((int) $source->id, $lang, $targetId, 'done', 'Adopted existing translated article.');
                    if (!empty($settings['repair_workflow_associations'])) {
                        $this->ensureWorkflowAssociation($targetId, (int) $source->id);
                    }
                    if (!empty($settings['create_article_associations'])) {
                        $this->ensureLanguageAssociation((int) $source->id, $targetId, $lang);
                    }
                    $count++;
                }
            }
        }

        return $count;
    }

    private function findArticleByXreference(int $sourceId, string $lang): int
    {
        // Backward-compatible method name: from v1.0.15 it intentionally
        // avoids #__content.xreference because that column is not guaranteed
        // to exist on the target Joomla 6 installation.
        $db = Factory::getDbo();

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('target_article_id'))
                ->from($db->quoteName('#__dnaaitranslator_map'))
                ->where($db->quoteName('source_article_id') . ' = :source')
                ->where($db->quoteName('target_language') . ' = :lang')
                ->where($db->quoteName('target_article_id') . ' > 0')
                ->bind(':source', $sourceId, ParameterType::INTEGER)
                ->bind(':lang', $lang);

            $id = (int) $db->setQuery($query)->loadResult();

            if ($id > 0) {
                return $id;
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return 0;
    }

    private function findOldSuffixedArticle(string $sourceTitle, int $sourceCatid, string $lang): int
    {
        $db = Factory::getDbo();
        $suffix = '[' . ($this->langSuffix[$lang] ?? '') . ']';

        if ($suffix === '[]') {
            return 0;
        }

        $cleanSource = $this->stripLanguageSuffix($sourceTitle);
        $like = $db->quote($db->escape($cleanSource, true) . ' %' . $db->escape($suffix, true), false);

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('language') . ' = :lang')
            ->where($db->quoteName('state') . ' IN (0,1)')
            ->where($db->quoteName('title') . ' LIKE ' . $like)
            ->bind(':lang', $lang)
            ->order($db->quoteName('id') . ' ASC');

        return (int) $db->setQuery($query, 0, 1)->loadResult();
    }

    private function getArticle(int $id): ?object
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        return $db->setQuery($query)->loadObject() ?: null;
    }

    private function uniqueAlias(string $table, string $title, int $parentOrCatId, string $lang, string $extension = ''): string
    {
        $db = Factory::getDbo();
        $base = OutputFilter::stringURLSafe($this->stripLanguageSuffix($title));

        if ($base === '') {
            $base = 'dna-ai-' . strtolower(str_replace('-', '-', $lang));
        }

        $alias = $base;
        $i = 2;

        while ($this->aliasExists($table, $alias, $parentOrCatId, $lang, $extension)) {
            $alias = $base . '-' . $i;
            $i++;
        }

        return $alias;
    }

    private function aliasExists(string $table, string $alias, int $parentOrCatId, string $lang, string $extension = ''): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($table))
            ->where($db->quoteName('alias') . ' = :alias')
            ->where($db->quoteName('language') . ' = :lang')
            ->bind(':alias', $alias)
            ->bind(':lang', $lang);

        if ($table === '#__content') {
            $query->where($db->quoteName('catid') . ' = :catid')
                ->bind(':catid', $parentOrCatId, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('parent_id') . ' = :parent')
                ->bind(':parent', $parentOrCatId, ParameterType::INTEGER);

            if ($extension !== '') {
                $query->where($db->quoteName('extension') . ' = :extension')
                    ->bind(':extension', $extension);
            }
        }

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    private function stripLanguageSuffix(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s*\[(EN|DE|FR|ES|IT)\]\s*/iu', ' ', $value) ?? $value;
        $value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function tableExists(string $table): bool
    {
        try {
            $db = Factory::getDbo();
            $name = str_replace('#__', $db->getPrefix(), $table);
            return in_array($name, $db->getTableList(), true);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
