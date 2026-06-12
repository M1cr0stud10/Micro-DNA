<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_dnaaitranslator
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('bootstrap.tooltip');

$settings = $this->settings;
$languages = $this->languageStatus;
$stats = $this->stats;
$runChecks = $this->runChecks;
$recentLog = $this->recentLog;
$nextPair = $this->nextPair;
$maskedKey = $settings['api_key'] !== '' ? str_repeat('•', min(12, strlen($settings['api_key']))) : '';
?>
<div class="dnaaitranslator dashboard">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <strong><?php echo Text::_('COM_DNAAITRANSLATOR_BACKEND_VERSION'); ?> <?php echo htmlspecialchars($this->version, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <?php echo Text::_('COM_DNAAITRANSLATOR_HELP_INTRO'); ?>
                    </p>
                    <div class="alert alert-info mb-0">
                        <?php echo Text::_('COM_DNAAITRANSLATOR_HELP_BATCH'); ?>
                    </div>
                </div>
            </div>

            <form action="<?php echo Route::_('index.php?option=com_dnaaitranslator'); ?>" method="post" id="adminForm" name="adminForm" class="form-validate">
                <div class="card mb-3">
                    <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_SETTINGS'); ?></strong></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label"><?php echo Text::_('COM_DNAAITRANSLATOR_MODE'); ?></label>
                            <div class="col-sm-9">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jform[mode]" id="jform_mode_test" value="test" <?php echo $settings['mode'] === 'test' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_mode_test"><?php echo Text::_('COM_DNAAITRANSLATOR_MODE_TEST'); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jform[mode]" id="jform_mode_live" value="live" <?php echo $settings['mode'] === 'live' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_mode_live"><?php echo Text::_('COM_DNAAITRANSLATOR_MODE_LIVE'); ?></label>
                                </div>
                                <div class="form-text"><?php echo Text::_('COM_DNAAITRANSLATOR_MODE_DESC'); ?></div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label" for="jform_api_key"><?php echo Text::_('COM_DNAAITRANSLATOR_API_KEY'); ?></label>
                            <div class="col-sm-9">
                                <input type="password" name="jform[api_key]" id="jform_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['api_key'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                <?php if ($maskedKey) : ?>
                                    <div class="form-text"><?php echo Text::sprintf('COM_DNAAITRANSLATOR_API_KEY_SAVED', $maskedKey); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label" for="jform_model"><?php echo Text::_('COM_DNAAITRANSLATOR_MODEL'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" name="jform[model]" id="jform_model" class="form-control" value="<?php echo htmlspecialchars($settings['model'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label" for="jform_api_timeout"><?php echo Text::_('COM_DNAAITRANSLATOR_TIMEOUT'); ?></label>
                            <div class="col-sm-9">
                                <input type="number" min="10" max="110" name="jform[api_timeout]" id="jform_api_timeout" class="form-control" value="<?php echo (int) $settings['api_timeout']; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label" for="jform_max_items"><?php echo Text::_('COM_DNAAITRANSLATOR_MAX_ITEMS'); ?></label>
                            <div class="col-sm-9">
                                <input type="number" min="1" max="50" name="jform[max_items]" id="jform_max_items" class="form-control" value="<?php echo (int) $settings['max_items']; ?>">
                                <div class="form-text"><?php echo Text::_('COM_DNAAITRANSLATOR_MAX_ITEMS_DESC'); ?></div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label"><?php echo Text::_('COM_DNAAITRANSLATOR_TARGET_LANGUAGES'); ?></label>
                            <div class="col-sm-9">
                                <?php foreach ($languages as $tag => $row) : ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="jform[target_languages][]" id="target_<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($tag, $settings['target_languages'], true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="target_<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></label>
                                    </div>
                                <?php endforeach; ?>
                                <div class="form-text"><?php echo Text::_('COM_DNAAITRANSLATOR_TARGET_LANGUAGES_DESC'); ?></div>
                            </div>
                        </div>

                        <hr>

                        <h4 class="h5 mb-3"><?php echo Text::_('COM_DNAAITRANSLATOR_TRANSLATE_FLAGS'); ?></h4>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label"><?php echo Text::_('COM_DNAAITRANSLATOR_TRANSLATE_FLAGS_LABEL'); ?></label>
                            <div class="col-sm-9">
                                <input type="hidden" name="jform[translate_articles]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jform[translate_articles]" id="jform_translate_articles" value="1" <?php echo !empty($settings['translate_articles']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_translate_articles"><?php echo Text::_('COM_DNAAITRANSLATOR_TRANSLATE_ARTICLES'); ?></label>
                                </div>

                                <input type="hidden" name="jform[translate_categories]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jform[translate_categories]" id="jform_translate_categories" value="1" <?php echo !empty($settings['translate_categories']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_translate_categories"><?php echo Text::_('COM_DNAAITRANSLATOR_TRANSLATE_CATEGORIES'); ?></label>
                                </div>

                                <input type="hidden" name="jform[create_article_associations]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jform[create_article_associations]" id="jform_create_article_associations" value="1" <?php echo !empty($settings['create_article_associations']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_create_article_associations"><?php echo Text::_('COM_DNAAITRANSLATOR_CREATE_ARTICLE_ASSOCIATIONS'); ?></label>
                                </div>

                                <input type="hidden" name="jform[create_category_associations]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jform[create_category_associations]" id="jform_create_category_associations" value="1" <?php echo !empty($settings['create_category_associations']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_create_category_associations"><?php echo Text::_('COM_DNAAITRANSLATOR_CREATE_CATEGORY_ASSOCIATIONS'); ?></label>
                                </div>

                                <input type="hidden" name="jform[repair_workflow_associations]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jform[repair_workflow_associations]" id="jform_repair_workflow_associations" value="1" <?php echo !empty($settings['repair_workflow_associations']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_repair_workflow_associations"><?php echo Text::_('COM_DNAAITRANSLATOR_REPAIR_WORKFLOW_ASSOCIATIONS'); ?></label>
                                </div>
                                <div class="form-text"><?php echo Text::_('COM_DNAAITRANSLATOR_TRANSLATE_FLAGS_DESC'); ?></div>
                            </div>
                        </div>

                        <hr>

                        <h4 class="h5 mb-3"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_SELECTION'); ?></h4>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_FILTERS'); ?></label>
                            <div class="col-sm-9">
                                <input type="hidden" name="jform[source_filter_categories_enabled]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jform[source_filter_categories_enabled]" id="jform_source_filter_categories_enabled" value="1" <?php echo !empty($settings['source_filter_categories_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_source_filter_categories_enabled"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_FILTER_CATEGORIES'); ?></label>
                                </div>
                                <input type="hidden" name="jform[source_filter_articles_enabled]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jform[source_filter_articles_enabled]" id="jform_source_filter_articles_enabled" value="1" <?php echo !empty($settings['source_filter_articles_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_source_filter_articles_enabled"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_FILTER_ARTICLES'); ?></label>
                                </div>
                                <div class="form-text"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_SELECTION_DESC'); ?></div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label" for="jform_source_category_ids"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_CATEGORY_IDS'); ?></label>
                            <div class="col-sm-9">
                                <textarea name="jform[source_category_ids]" id="jform_source_category_ids" class="form-control" rows="2"><?php echo htmlspecialchars(implode(', ', $settings['source_category_ids']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="form-text"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_CATEGORY_IDS_DESC'); ?></div>
                                <input type="hidden" name="jform[source_include_child_categories]" value="0">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="jform[source_include_child_categories]" id="jform_source_include_child_categories" value="1" <?php echo !empty($settings['source_include_child_categories']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="jform_source_include_child_categories"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_INCLUDE_CHILDREN'); ?></label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label" for="jform_source_article_ids"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_ARTICLE_IDS'); ?></label>
                            <div class="col-sm-9">
                                <textarea name="jform[source_article_ids]" id="jform_source_article_ids" class="form-control" rows="2"><?php echo htmlspecialchars(implode(', ', $settings['source_article_ids']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="form-text"><?php echo Text::_('COM_DNAAITRANSLATOR_SOURCE_ARTICLE_IDS_DESC'); ?></div>
                            </div>
                        </div>

                        <button type="submit" name="task" value="save" class="btn btn-primary">
                            <?php echo Text::_('JSAVE'); ?>
                        </button>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_ACTIONS'); ?></strong></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="task" value="startTranslation" class="btn btn-success">
                                <?php echo Text::_('COM_DNAAITRANSLATOR_START_TRANSLATION'); ?>
                            </button>
                            <button type="submit" name="task" value="repairIncomplete" class="btn btn-warning">
                                <?php echo Text::_('COM_DNAAITRANSLATOR_REPAIR_BUTTON'); ?>
                            </button>
                            <button type="submit" name="task" value="resetState" class="btn btn-outline-danger" onclick="return confirm('<?php echo Text::_('COM_DNAAITRANSLATOR_RESET_CONFIRM', true); ?>');">
                                <?php echo Text::_('COM_DNAAITRANSLATOR_RESET_STATE'); ?>
                            </button>
                        </div>
                        <div class="form-text mt-2"><?php echo Text::_('COM_DNAAITRANSLATOR_ACTIONS_DESC'); ?></div>
                    </div>
                </div>

                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_LANG_STATUS'); ?></strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th><?php echo Text::_('COM_DNAAITRANSLATOR_LANGUAGE'); ?></th>
                                <th><?php echo Text::_('COM_DNAAITRANSLATOR_INSTALLED'); ?></th>
                                <th><?php echo Text::_('COM_DNAAITRANSLATOR_PUBLISHED'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($languages as $tag => $row) : ?>
                                <tr class="<?php echo $row['ok'] ? 'table-success' : 'table-danger'; ?>">
                                    <td><strong><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo ($row['installed'] && $row['enabled']) ? 'OK' : 'NO'; ?></td>
                                    <td><?php echo $row['published'] ? 'OK' : 'NO'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_MULTILANG_STATUS'); ?></strong></div>
                <div class="card-body">
                    <p class="mb-1"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_ASSOC_ARTICLES_STATUS'); ?></strong>: <?php echo !empty($settings['create_article_associations']) ? 'ON' : 'OFF'; ?></p>
                    <p class="mb-1"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_ASSOC_CATEGORIES_STATUS'); ?></strong>: <?php echo !empty($settings['create_category_associations']) ? 'ON' : 'OFF'; ?></p>
                    <p class="mb-0"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_MENU_STATUS'); ?></strong>: <?php echo Text::_('COM_DNAAITRANSLATOR_MENU_STATUS_MANUAL'); ?></p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_RUN_CHECKS'); ?></strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <tbody>
                            <?php foreach ($runChecks as $check) : ?>
                                <?php $level = in_array($check['level'] ?? '', ['success', 'warning', 'danger'], true) ? $check['level'] : 'secondary'; ?>
                                <tr class="table-<?php echo $level; ?>">
                                    <td><strong><?php echo htmlspecialchars((string) ($check['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars((string) ($check['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_STATS'); ?></strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_SOURCES'); ?></dt><dd class="col-5 text-end"><?php echo (int) $stats['sources']; ?></dd>
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_TOTAL'); ?></dt><dd class="col-5 text-end"><?php echo (int) $stats['total_pairs']; ?></dd>
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_DONE'); ?></dt><dd class="col-5 text-end"><?php echo (int) ($stats['done'] ?? 0); ?></dd>
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_PENDING'); ?></dt><dd class="col-5 text-end"><?php echo (int) ($stats['pending'] ?? 0); ?></dd>
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_ERRORS'); ?></dt><dd class="col-5 text-end"><?php echo (int) ($stats['error'] ?? 0); ?></dd>
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_ASSET_ARTICLES'); ?></dt><dd class="col-5 text-end"><?php echo (int) $stats['asset_zero_articles']; ?></dd>
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_ASSET_CATEGORIES'); ?></dt><dd class="col-5 text-end"><?php echo (int) $stats['asset_zero_categories']; ?></dd>
                        <dt class="col-7"><?php echo Text::_('COM_DNAAITRANSLATOR_STATS_WORKFLOW'); ?></dt><dd class="col-5 text-end"><?php echo (int) $stats['missing_workflows']; ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_NEXT_PAIR'); ?></strong></div>
                <div class="card-body">
                    <?php if ($nextPair) : ?>
                        <p class="mb-1"><strong>ID <?php echo (int) $nextPair['source']->id; ?></strong> — <?php echo htmlspecialchars($nextPair['source']->title, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mb-0"><span class="badge bg-primary"><?php echo htmlspecialchars($nextPair['target_language'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                    <?php else : ?>
                        <p class="mb-0 text-success"><?php echo Text::_('COM_DNAAITRANSLATOR_NO_NEXT_PAIR'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong><?php echo Text::_('COM_DNAAITRANSLATOR_BACKEND_LOG'); ?></strong></div>
                <div class="card-body p-0">
                    <?php if (!empty($recentLog)) : ?>
                        <table class="table table-sm mb-0 align-middle dnaaitranslator-log">
                            <thead>
                                <tr>
                                    <th><?php echo Text::_('COM_DNAAITRANSLATOR_LOG_UPDATED'); ?></th>
                                    <th><?php echo Text::_('COM_DNAAITRANSLATOR_LOG_SOURCE'); ?></th>
                                    <th><?php echo Text::_('COM_DNAAITRANSLATOR_LOG_TARGET'); ?></th>
                                    <th><?php echo Text::_('COM_DNAAITRANSLATOR_LOG_STATUS'); ?></th>
                                    <th><?php echo Text::_('COM_DNAAITRANSLATOR_LOG_NOTES'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLog as $row) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($row['updated'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            ID <?php echo (int) ($row['source_article_id'] ?? 0); ?><br>
                                            <small><?php echo htmlspecialchars((string) ($row['source_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars((string) ($row['target_language'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
                                            <?php if (!empty($row['target_article_id'])) : ?>
                                                <small>ID <?php echo (int) $row['target_article_id']; ?> <?php echo htmlspecialchars((string) ($row['target_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo htmlspecialchars((string) ($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="p-3 mb-0"><?php echo Text::_('COM_DNAAITRANSLATOR_LOG_EMPTY'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
