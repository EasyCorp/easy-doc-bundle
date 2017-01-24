<?php

namespace EasyCorp\Bundle\EasyDocBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

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
        $params['routes'] = $this->getRoutes();
        $params['services'] = $this->getServices();
        $params['packages'] = $this->getPackages();

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

            $routes[] = $route;
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
            $service['id'] = $serviceId;
            $service['class'] = $definition->getClass() ?: '-';
            $service['public'] = $definition->isPublic() ? 'yes' : 'no';
            $service['synthetic'] = $definition->isSynthetic() ? 'yes' : 'no';
            $service['lazy'] = $definition->isLazy() ? 'yes' : 'no';
            $service['shared'] = $definition->isShared() ? 'yes' : 'no';
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
        foreach ($composerLockContents['packages'] as $packageConfig) {
            $package = array();
            foreach (array('name', 'description', 'version', 'license', 'homepage', 'type', 'source') as $key) {
                $package[$key] = isset($packageConfig[$key]) ? $packageConfig[$key] : '';
            }

            $packages[] = $package;
        }

        return $packages;
    }
}
