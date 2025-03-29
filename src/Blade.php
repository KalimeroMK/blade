<?php

namespace Jenssegers\Blade;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as FactoryContract;
use Illuminate\Contracts\View\View;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Factory;
use Illuminate\View\ViewServiceProvider;

class Blade implements FactoryContract
{
    /**
     * @var Application
     */
    protected $container;

    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var BladeCompiler
     */
    private $compiler;

    public function __construct($viewPaths, string $cachePath, ?ContainerInterface $container = null)
    {
        $this->container = $container ?: new Container;

        $this->setupContainer((array) $viewPaths, $cachePath);
        (new ViewServiceProvider($this->container))->register();

        $this->factory = $this->container->get('view');
        $this->compiler = $this->container->get('blade.compiler');
    }

    public function render(string $view, array $data = [], array $mergeData = []): string
    {
        return $this->make($view, $data, $mergeData)->render();
    }

    public function make($view, $data = [], $mergeData = []): View
    {
        return $this->factory->make($view, $data, $mergeData);
    }

    public function compiler(): BladeCompiler
    {
        return $this->compiler;
    }

    public function directive(string $name, callable $handler)
    {
        $this->compiler->directive($name, $handler);
    }

    public function if($name, callable $callback)
    {
        $this->compiler->if($name, $callback);
    }

    public function exists($view): bool
    {
        return $this->factory->exists($view);
    }

    public function file($path, $data = [], $mergeData = []): View
    {
        return $this->factory->file($path, $data, $mergeData);
    }

    public function share($key, $value = null)
    {
        return $this->factory->share($key, $value);
    }

    public function composer($views, $callback): array
    {
        return $this->factory->composer($views, $callback);
    }

    public function creator($views, $callback): array
    {
        return $this->factory->creator($views, $callback);
    }

    public function addNamespace($namespace, $hints): self
    {
        $this->factory->addNamespace($namespace, $hints);

        return $this;
    }

    public function replaceNamespace($namespace, $hints): self
    {
        $this->factory->replaceNamespace($namespace, $hints);

        return $this;
    }

    public function __call(string $method, array $params)
    {
        return call_user_func_array([$this->factory, $method], $params);
    }

    protected function setupContainer(array $viewPaths, string $cachePath)
    {
        // Регистрирање на основни сервиси
        $this->container->bindIf('files', fn () => new Filesystem);
        $this->container->bindIf('events', fn () => new Dispatcher);
        $this->container->bindIf('config', fn () => new Repository([
            'view.paths' => $viewPaths,
            'view.compiled' => $cachePath,
        ]));

        // Регистрирање на BladeCompiler
        $this->container->bindIf('blade.compiler', function ($container) {
            return new BladeCompiler(
                $container->get('files'),
                $container->get('config')->get('view.compiled')
            );
        });

        // Регистрирање на EngineResolver (потребно за Blade)
        $this->container->bindIf('view.engine.resolver', function () {
            $resolver = new \Illuminate\View\Engines\EngineResolver();
            $resolver->register('blade', function () {
                return new \Illuminate\View\Engines\CompilerEngine($this->container->get('blade.compiler'));
            });

            return $resolver;
        });

        // Регистрирање на ViewFactory
        $this->container->bindIf('view.finder', function ($container) {
            return new \Illuminate\View\FileViewFinder(
                $container->get('files'),
                $container->get('config')->get('view.paths')
            );
        });

        $this->container->bindIf('view', function ($container) {
            return new ViewFactory(
                $container->get('view.engine.resolver'),
                $container->get('view.finder'),
                $container->get('events')
            );
        });

        Facade::setFacadeApplication($this->container);
    }
}
