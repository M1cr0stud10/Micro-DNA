<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_dnaaitranslator
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

class com_dnaaitranslatorInstallerScript
{
    private string $version = '1.0.18';

    public function install($parent): bool
    {
        $this->ensureParams(true);
        return true;
    }

    public function update($parent): bool
    {
        $this->ensureParams(false);
        return true;
    }

    public function postflight($type, $parent): bool
    {
        $this->ensureSchema();
        $this->ensureParams($type === 'install');
        $this->clearAutoloadCache();
        return true;
    }

    private function ensureSchema(): void
    {
        try {
            $db = Factory::getDbo();
            $columns = $db->getTableColumns('#__dnaaitranslator_map', false);

            if (!$columns) {
                return;
            }

            if (!array_key_exists('notes', $columns)) {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__dnaaitranslator_map') . ' ADD ' . $db->quoteName('notes') . ' varchar(1000) NOT NULL DEFAULT ' . $db->quote('') . ' AFTER ' . $db->quoteName('status'))->execute();
                $columns = $db->getTableColumns('#__dnaaitranslator_map', false);
            }
        } catch (\Throwable $e) {
            // Never block component installation/update only because the optional notes column could not be checked.
        }
    }

    private function clearAutoloadCache(): void
    {
        if (defined('JPATH_ADMINISTRATOR')) {
            $file = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function ensureParams(bool $isFirstInstall): void
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_dnaaitranslator'));
        $row = $db->setQuery($query)->loadObject();

        if (!$row) {
            return;
        }

        $params = new Registry((string) $row->params);

        if ($isFirstInstall && !$params->exists('mode')) {
            $params->set('mode', 'test');
        }

        if (!$params->exists('target_languages')) {
            $params->set('target_languages', ['en-GB', 'de-DE', 'fr-FR', 'es-ES']);
        }

        if (!$params->exists('model')) {
            $params->set('model', 'gpt-4o-mini');
        }

        if (!$params->exists('api_timeout')) {
            $params->set('api_timeout', 55);
        }

        if (!$params->exists('max_items')) {
            $params->set('max_items', 1);
        }

        if (!$params->exists('translate_articles')) {
            $params->set('translate_articles', 1);
        }

        if (!$params->exists('translate_categories')) {
            $params->set('translate_categories', 1);
        }

        if (!$params->exists('create_article_associations')) {
            $params->set('create_article_associations', 1);
        }

        if (!$params->exists('create_category_associations')) {
            $params->set('create_category_associations', 1);
        }

        if (!$params->exists('repair_workflow_associations')) {
            $params->set('repair_workflow_associations', 1);
        }

        if (!$params->exists('source_scope')) {
            $params->set('source_scope', 'all');
        }

        if (!$params->exists('source_filter_categories_enabled')) {
            $params->set('source_filter_categories_enabled', ((string) $params->get('source_scope', 'all')) === 'categories' ? 1 : 0);
        }

        if (!$params->exists('source_filter_articles_enabled')) {
            $params->set('source_filter_articles_enabled', ((string) $params->get('source_scope', 'all')) === 'articles' ? 1 : 0);
        }

        if (!$params->exists('source_article_ids')) {
            $params->set('source_article_ids', []);
        }

        if (!$params->exists('source_category_ids')) {
            $params->set('source_category_ids', []);
        }

        if (!$params->exists('source_include_child_categories')) {
            $params->set('source_include_child_categories', 1);
        }

        $params->set('version_backend_visible', $this->version);

        $paramsJson = $params->toString('JSON');
        $extensionId = (int) $row->extension_id;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $paramsJson)
            ->bind(':id', $extensionId, \Joomla\Database\ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }
}
