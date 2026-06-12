<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_dnaaitranslator
 */

namespace Dna\Component\DnaAiTranslator\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Dna\Component\DnaAiTranslator\Administrator\Service\TranslatorService;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array $settings = [];
    public array $languageStatus = [];
    public array $stats = [];
    public array $runChecks = [];
    public array $recentLog = [];
    public ?array $nextPair = null;
    public string $version = '1.0.18';

    public function display($tpl = null): void
    {
        $service = new TranslatorService();

        $this->settings       = $service->getSettings();
        $this->languageStatus = $service->getLanguageStatus();
        $this->stats          = $service->getStats();
        $this->runChecks      = $service->getRunChecks();
        $this->recentLog      = $service->getRecentLog(20);
        $this->nextPair       = $service->peekNextPair();

        ToolbarHelper::title(Text::_('COM_DNAAITRANSLATOR_TITLE') . ' <small>v' . $this->version . '</small>', 'language');
        ToolbarHelper::preferences('com_dnaaitranslator');

        parent::display($tpl);
    }
}
