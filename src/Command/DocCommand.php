<?php

namespace EasyCorp\Bundle\EasyDocBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class DocCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('doc')
            ->setDescription('Generates the entire documentation of your Symfony application')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $params['project_name'] = $this->getProjectName();
        $params['easydoc_version'] = $this->getEasyDocVersion();
        $params['routes'] = $this->getRoutes();
        $params['services'] = $this->getServices();
        $params['packages'] = $this->getPackages();
        $params['bundles'] = $this->getBundles();
        $params['project_score'] = $this->getProjectScore($params);
        $params['last_build_date'] = new \DateTime();

        $docPath = $this->getContainer()->getParameter('kernel.cache_dir').'/doc.html';
        file_put_contents($docPath, $this->getContainer()->get('twig')->render('@EasyDoc/doc.html.twig', $params));
        $output->writeln(sprintf('[OK] The documentation was generated in %s', realpath($docPath)));
    }

    private function getProjectName()
    {
        $composerJsonPath = $this->getContainer()->getParameter('kernel.root_dir').'/../composer.json';
        if (file_exists($composerJsonPath)) {
            $composerJsonContents = json_decode(file_get_contents($composerJsonPath), true);
            list($vendorName, $projectName) = explode('/', $composerJsonContents['name']);
        } else {
            $projectName = basename(dirname($this->getContainer()->getParameter('kernel.root_dir')));
        }

        $humanizedProjectName = ucwords(strtr($projectName, '_-', '  '));

        return $humanizedProjectName;
    }

    private function getEasyDocVersion()
    {
        foreach ($this->getPackages() as $package) {
            if ('easycorp/easy-doc-bundle' === $package['name']) {
                return $package['version'];
            }
        }

        return 'v1.0.0';
    }

    private function getRoutes()
    {
        $allRoutes = $this->getContainer()->get('router')->getRouteCollection();
        $routes = array();
        foreach ($allRoutes->all() as $name => $routeObject) {
            $route['name'] = $name;
            $route['path'] = $routeObject->getPath();
            $route['path_regex'] = $routeObject->compile()->getRegex();
            $route['host'] = '' !== $routeObject->getHost() ? $routeObject->getHost() : '(any)';
            $route['host_regex'] = '' !== $routeObject->getHost() ? $routeObject->compile()->getHostRegex() : '';
            $route['http_methods'] = $routeObject->getMethods() ?: '(any)';
            $route['http_schemes'] = $routeObject->getSchemes() ?: '(any)';
            $route['php_class'] = get_class($routeObject);
            $route['defaults'] = $routeObject->getDefaults();
            //$route['requirements'] = $routeObject->getRequirements() ?: '(none)',
            //$route['options'] = $this->formatRouterConfig($route->getOptions();

            if (in_array($name, $this->getSymfonyBuiltInRouteNames())) {
                $routes['symfony'][] = $route;
            } else {
                $routes['application'][] = $route;
            }
        }

        return $routes;
    }

    private function getServices()
    {
        $cachedFile = $this->getContainer()->getParameter('debug.container.dump');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        $services = array();
        foreach ($this->getContainer()->getServiceIds() as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $isShared = method_exists($definition, 'isShared') ? $definition->isShared() : 'prototype' !== $definition->getScope();
            $service['id'] = $serviceId;
            $service['class'] = $definition->getClass() ?: '-';
            $service['public'] = $definition->isPublic() ? 'yes' : 'no';
            $service['synthetic'] = $definition->isSynthetic() ? 'yes' : 'no';
            $service['lazy'] = $definition->isLazy() ? 'yes' : 'no';
            $service['shared'] = $isShared ? 'yes' : 'no';
            $service['abstract'] = $definition->isAbstract() ? 'yes' : 'no';
            $service['tags'] = $definition->getTags();
            $service['method_calls'] = $definition->getMethodCalls();
            $service['factory'] = $definition->getFactory();

            $services[] = $service;
        }

        return $services;
    }

    private function getPackages()
    {
        $packages = array();

        $composerLockPath = $this->getContainer()->getParameter('kernel.root_dir').'/../composer.lock';
        if (!file_exists($composerLockPath)) {
            return $packages;
        }

        $composerLockContents = json_decode(file_get_contents($composerLockPath), true);
        $prodPackages = $this->processComposerPackagesInformation($composerLockContents['packages']);
        $devPackages = $this->processComposerPackagesInformation($composerLockContents['packages-dev'], true);
        $allPackages = array_merge($prodPackages, $devPackages);
        ksort($allPackages);

        return $allPackages;
    }

    private function getBundles()
    {
        $bundles = array();
        $rootDir = realpath($this->getContainer()->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR;

        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundleName => $bundleObject) {
            $bundle = array(
                'name' => $bundleObject->getName(),
                'parent' => $bundleObject->getParent(),
                'namespace' => $bundleObject->getNamespace(),
                'path' => str_replace($rootDir, '', $bundleObject->getPath()),
            );

            $stats = $this->getBundleDirSize($bundleObject);
            $bundle['num_files'] = $stats['num_files'];
            $bundle['size'] = $stats['size'];

            $bundles[$bundleObject->getName()] = $bundle;
        }

        ksort($bundles);

        return $bundles;
    }

    private function processComposerPackagesInformation($composerPackages, $isDev = false)
    {
        $packages = array();
        foreach ($composerPackages as $packageConfig) {
            $package = array();
            $package['is_dev'] = $isDev;
            foreach (array('name', 'description', 'keywords', 'authors', 'version', 'license', 'homepage', 'type', 'source', 'bin', 'autoload', 'time') as $key) {
                $package[$key] = isset($packageConfig[$key]) ? $packageConfig[$key] : '';
            }

            $packages[$package['name']] = $package;
        }

        return $packages;
    }

    private function getSymfonyBuiltInRouteNames()
    {
        return array(
            '_profiler',
            '_profiler_exception',
            '_profiler_exception_css',
            '_profiler_home',
            '_profiler_info',
            '_profiler_open_file',
            '_profiler_phpinfo',
            '_profiler_router',
            '_profiler_search',
            '_profiler_search_bar',
            '_profiler_search_results',
            '_twig_error_test',
            '_wdt',
        );
    }

    private function getBundleDirSize(BundleInterface $bundle)
    {
        $dirSize = 0;
        $numFiles = 0;
        $dirItems = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($bundle->getPath()));
        foreach ($dirItems as $item) {
            if ($item->isFile()) {
                $dirSize += $item->getSize();
                $numFiles++;
            }
        }

        return array(
            'num_files' => $numFiles,
            'size' => $dirSize,
        );
    }

    private function getProjectScore($params)
    {
        $score = 0;

        $score += 1 * count($params['routes']['symfony']);
        $score += 5 * count($params['routes']['application']);
        $score += 10 * count($params['services']);
        foreach ($params['packages'] as $package) {
            $score += $package['is_dev'] ? 25 : 50;
        }
        foreach ($params['bundles'] as $bundle) {
            $score += 'vendor/' === substr($bundle['path'], 0, 7) ? 25 : 100;
        }

        return $score;
    }
}
