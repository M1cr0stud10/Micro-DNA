<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_dnaaitranslator
 */

namespace Dna\Component\DnaAiTranslator\Administrator\Controller;

defined('_JEXEC') or die;

use Dna\Component\DnaAiTranslator\Administrator\Service\TranslatorService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    public function __construct($config = [], $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('save', 'saveSettings');
        $this->registerTask('saveSettings', 'saveSettings');
        $this->registerTask('startTranslation', 'startTranslation');
        $this->registerTask('translateNext', 'startTranslation');
        $this->registerTask('repairIncomplete', 'repairIncomplete');
        $this->registerTask('resetState', 'resetState');
    }

    public function display($cachable = false, $urlparams = [])
    {
        return parent::display($cachable, $urlparams);
    }

    public function saveSettings(): void
    {
        $this->checkBackendToken();

        try {
            (new TranslatorService())->saveSettings($this->getFormData());
            $this->app->enqueueMessage(Text::_('COM_DNAAITRANSLATOR_SETTINGS_SAVED'), 'success');
        } catch (\Throwable $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_dnaaitranslator', false));
    }

    public function startTranslation(): void
    {
        $this->checkBackendToken();

        try {
            $service = new TranslatorService();

            // The run button is a real task and also saves the current backend fields first,
            // so LIVE/manuale and all operational flags cannot silently fall back to old values.
            $service->saveSettings($this->getFormData());
            $result = $service->startTranslation();

            $type = !empty($result['done']) ? 'success' : (!empty($result['blocked']) ? 'warning' : 'message');
            $this->app->enqueueMessage($result['message'] ?? Text::_('COM_DNAAITRANSLATOR_TRANSLATION_STEP_DONE'), $type);
        } catch (\Throwable $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_dnaaitranslator', false));
    }

    public function repairIncomplete(): void
    {
        $this->checkBackendToken();

        try {
            $service = new TranslatorService();
            $service->saveSettings($this->getFormData());
            $result = $service->repairIncomplete();
            $this->app->enqueueMessage($result['message'] ?? Text::_('COM_DNAAITRANSLATOR_REPAIR_DONE'), 'success');
        } catch (\Throwable $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_dnaaitranslator', false));
    }

    public function resetState(): void
    {
        $this->checkBackendToken();

        try {
            (new TranslatorService())->resetState();
            $this->app->enqueueMessage(Text::_('COM_DNAAITRANSLATOR_STATE_RESET_DONE'), 'success');
        } catch (\Throwable $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_dnaaitranslator', false));
    }

    private function getFormData(): array
    {
        $data = $this->input->get('jform', [], 'array');

        return is_array($data) ? $data : [];
    }

    private function checkBackendToken(): void
    {
        if (!Session::checkToken()) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'));
        }

        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_dnaaitranslator')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
        }
    }
}
