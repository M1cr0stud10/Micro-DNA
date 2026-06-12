<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_dnaaitranslator
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $namespace = 'Dna\\Component\\DnaAiTranslator';

        /**
         * Guard against stale or missing PSR-4 cache.
         * Without this, Joomla can fail to autoload:
         * Dna\Component\DnaAiTranslator\Administrator\Controller\DisplayController
         * and shows "Classe di controllo non valida: display".
         */
        $this->registerLocalAutoloader($namespace);

        $container->registerServiceProvider(new MVCFactory($namespace));
        $container->registerServiceProvider(new ComponentDispatcherFactory($namespace));

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                $component = new MVCComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );

                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }

    private function registerLocalAutoloader(string $namespace): void
    {
        $adminSrc = JPATH_ADMINISTRATOR . '/components/com_dnaaitranslator/src';
        $siteSrc  = JPATH_SITE . '/components/com_dnaaitranslator/src';

        spl_autoload_register(
            static function (string $class) use ($namespace, $adminSrc, $siteSrc): void {
                $prefixAdmin = $namespace . '\\Administrator\\';
                $prefixSite  = $namespace . '\\Site\\';

                if (strncmp($class, $prefixAdmin, strlen($prefixAdmin)) === 0) {
                    $relative = substr($class, strlen($prefixAdmin));
                    $file = $adminSrc . '/' . str_replace('\\', '/', $relative) . '.php';

                    if (is_file($file)) {
                        require_once $file;
                    }

                    return;
                }

                if (strncmp($class, $prefixSite, strlen($prefixSite)) === 0) {
                    $relative = substr($class, strlen($prefixSite));
                    $file = $siteSrc . '/' . str_replace('\\', '/', $relative) . '.php';

                    if (is_file($file)) {
                        require_once $file;
                    }
                }
            },
            true,
            true
        );
    }
};
