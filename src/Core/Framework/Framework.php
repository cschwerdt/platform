<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ActionEventCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\FrameworkExtension;
use Shopware\Core\Framework\Migration\MigrationCompilerPass;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Framework extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new FrameworkExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->setParameter('locale', 'en-GB');
        $environment = $container->getParameter('kernel.environment');

        $this->buildConfig($container, $environment);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
        $loader->load('api.xml');
        $loader->load('custom-field.xml');
        $loader->load('data-abstraction-layer.xml');
        $loader->load('demodata.xml');
        $loader->load('event.xml');
        $loader->load('filesystem.xml');
        $loader->load('message-queue.xml');
        $loader->load('plugin.xml');
        $loader->load('rule.xml');
        $loader->load('scheduled-task.xml');
        $loader->load('store.xml');
        $loader->load('language.xml');

        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('services_test.xml');
        }

        $container->addCompilerPass(new FeatureFlagCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new MigrationCompilerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new ActionEventCompilerPass());

        parent::build($container);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerEntityExtensions();
    }

    protected function registerMigrationPath(ContainerBuilder $container): void
    {
        $directories = $container->getParameter('migration.directories');
        $directories['Shopware\Core\Migration'] = __DIR__ . '/../Migration';

        $container->setParameter('migration.directories', $directories);
    }

    protected function registerFilesystem(ContainerBuilder $container, string $key): void
    {
        // empty body intended to prevent circular filesystem references
    }

    private function buildConfig(ContainerBuilder $container, $environment): void
    {
        $locator = new FileLocator($this->getConfigPath());

        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/' . $this->getConfigPath();

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');
        $configLoader->load($confDir . '/{packages}/' . $environment . '/*' . Kernel::CONFIG_EXTS, 'glob');
    }

    private function registerEntityExtensions(): void
    {
        /** @var DefinitionInstanceRegistry $definitionRegistry */
        $definitionRegistry = $this->container->get(DefinitionInstanceRegistry::class);

        /** @var SalesChannelDefinitionInstanceRegistry $salesChannelRegistry */
        $salesChannelRegistry = $this->container->get(SalesChannelDefinitionInstanceRegistry::class);

        /** @var ExtensionRegistry $registry */
        $registry = $this->container->get(ExtensionRegistry::class);

        foreach ($registry->getExtensions() as $extension) {
            /** @var string $class */
            $class = $extension->getDefinitionClass();

            /** @var EntityDefinition $definition */
            $definition = $definitionRegistry->get($class);

            $definition->addExtension($extension);

            $salesChannelDefinition = $salesChannelRegistry->get($class);

            // same definition? do not added extension
            if (get_class($salesChannelDefinition) !== get_class($definition)) {
                $salesChannelDefinition->addExtension($extension);
            }
        }
    }
}
