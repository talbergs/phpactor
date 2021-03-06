<?php

namespace Phpactor\Extension\Rpc;

use Phpactor\Extension\Rpc\Command\RpcCommand;
use Phpactor\Extension\Rpc\RequestHandler\RequestHandler;
use Phpactor\Extension\Rpc\Handler\EchoHandler;
use Phpactor\Extension\Core\Rpc\StatusHandler;
use Phpactor\Extension\Completion\Rpc\CompleteHandler;
use Phpactor\Extension\Rpc\Handler\ClassSearchHandler;
use Phpactor\Extension\ClassMover\Rpc\ClassCopyHandler;
use Phpactor\Extension\ClassMover\Rpc\ClassMoveHandler;
use Phpactor\Extension\ClassMover\Rpc\ReferencesHandler;
use Phpactor\Extension\Rpc\Handler\OffsetInfoHandler;
use Phpactor\Extension\CodeTransform\Rpc\TransformHandler;
use Phpactor\Extension\CodeTransform\Rpc\ClassNewHandler;
use Phpactor\Extension\CodeTransform\Rpc\ClassInflectHandler;
use Phpactor\Extension\Rpc\Handler\ContextMenuHandler;
use Phpactor\Extension\CodeTransform\Rpc\ExtractConstantHandler;
use Phpactor\Extension\CodeTransform\Rpc\ExtractMethodHandler;
use Phpactor\Extension\CodeTransform\Rpc\GenerateMethodHandler;
use Phpactor\Extension\CodeTransform\Rpc\GenerateAccessorHandler;
use Phpactor\Extension\CodeTransform\Rpc\RenameVariableHandler;
use Phpactor\Extension\Rpc\RequestHandler\ExceptionCatchingHandler;
use Phpactor\Extension\Rpc\RequestHandler\LoggingHandler;
use Phpactor\Extension\Rpc\Handler\NavigateHandler;
use Phpactor\Extension\CodeTransform\Rpc\OverrideMethodHandler;
use Phpactor\Extension\Core\Rpc\CacheClearHandler;
use Phpactor\Extension\Core\Rpc\ConfigHandler;
use Phpactor\Extension\CodeTransform\Rpc\ImportClassHandler;
use Phpactor\Container\Extension;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Container;
use Phpactor\Container\Schema;
use Phpactor\Extension\SourceCodeFilesystem\SourceCodeFilesystemExtension;

class RpcExtension implements Extension
{
    const SERVICE_REQUEST_HANDLER = 'rpc.request_handler';

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $container->register('rpc.command.rpc', function (Container $container) {
            return new RpcCommand(
                $container->get('rpc.request_handler'),
                $container->get('config.paths'),
                $container->getParameter('rpc.store_replay')
            );
        }, [ 'ui.console.command' => [] ]);

        $container->register(self::SERVICE_REQUEST_HANDLER, function (Container $container) {
            return new LoggingHandler(
                new ExceptionCatchingHandler(
                    new RequestHandler($container->get('rpc.handler_registry'))
                ),
                $container->get('monolog.logger')
            );
        });

        $container->register('rpc.handler_registry', function (Container $container) {
            $handlers = [];
            foreach (array_keys($container->getServiceIdsForTag('rpc.handler')) as $serviceId) {
                $handlers[] = $container->get($serviceId);
            }

            return new HandlerRegistry($handlers);
        });

        $this->registerHandlers($container);
    }

    private function registerHandlers(ContainerBuilder $container)
    {
        $container->register('rpc.handler.echo', function (Container $container) {
            return new EchoHandler();
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.complete', function (Container $container) {
            return new CompleteHandler(
                $container->get('application.complete')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.class_search', function (Container $container) {
            return new ClassSearchHandler(
                $container->get('application.class_search')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.class_references', function (Container $container) {
            return new ReferencesHandler(
                $container->get('reflection.reflector'),
                $container->get('application.class_references'),
                $container->get('application.method_references'),
                $container->get('source_code_filesystem.registry')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.copy_class', function (Container $container) {
            return new ClassCopyHandler(
                $container->get('application.class_copy')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.move_class', function (Container $container) {
            return new ClassMoveHandler(
                $container->get('application.class_mover'),
                $container->getParameter('rpc.class_move.filesystem')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.offset_info', function (Container $container) {
            return new OffsetInfoHandler(
                $container->get('reflection.reflector')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.transform', function (Container $container) {
            return new TransformHandler(
                $container->get('code_transform.transform')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.class_new', function (Container $container) {
            return new ClassNewHandler(
                $container->get('application.class_new')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.class_inflect', function (Container $container) {
            return new ClassInflectHandler(
                $container->get('application.class_inflect')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.context_menu', function (Container $container) {
            return new ContextMenuHandler(
                $container->get('reflection.reflector'),
                $container->get('application.helper.class_file_normalizer'),
                json_decode(file_get_contents(__DIR__ . '/menu.json'), true),
                $container
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.extract_constant', function (Container $container) {
            return new ExtractConstantHandler(
                $container->get('code_transform.refactor.extract_constant')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.extract_method', function (Container $container) {
            return new ExtractMethodHandler(
                $container->get('code_transform.refactor.extract_method')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.generate_method', function (Container $container) {
            return new GenerateMethodHandler(
                $container->get('code_transform.refactor.generate_method')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.generate_accessor', function (Container $container) {
            return new GenerateAccessorHandler(
                $container->get('code_transform.refactor.generate_accessor')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.rename_variable', function (Container $container) {
            return new RenameVariableHandler(
                $container->get('code_transform.refactor.rename_variable')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.navigate', function (Container $container) {
            return new NavigateHandler(
                $container->get('application.navigator')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.override_method', function (Container $container) {
            return new OverrideMethodHandler(
                $container->get('reflection.reflector'),
                $container->get('code_transform.refactor.override_method')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.refactor.import_class', function (Container $container) {
            return new ImportClassHandler(
                $container->get('code_transform.refactor.class_import'),
                $container->get('application.class_search'),
                $container->getParameter('rpc.class_search.filesystem')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.cache_clear', function (Container $container) {
            return new CacheClearHandler(
                $container->get('application.cache_clear')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.status', function (Container $container) {
            return new StatusHandler(
                $container->get('application.status'),
                $container->get('config.paths')
            );
        }, [ 'rpc.handler' => [] ]);

        $container->register('rpc.handler.config', function (Container $container) {
            return new ConfigHandler($container->getParameters());
        }, [ 'rpc.handler' => [] ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Schema $schema)
    {
        $schema->setDefaults([
            'rpc.class_search.filesystem' => SourceCodeFilesystemExtension::FILESYSTEM_COMPOSER,
            'rpc.class_move.filesystem' => SourceCodeFilesystemExtension::FILESYSTEM_GIT,
            'rpc.store_replay' => false,
        ]);
    }
}
