<?php

namespace Phpactor\Extension\ClassMover\Rpc;

use Phpactor\Extension\ClassMover\Application\ClassCopy;
use Phpactor\Extension\Rpc\Response\OpenFileResponse;
use Phpactor\Extension\Rpc\Response\Input\TextInput;
use Phpactor\Extension\ClassMover\Application\Logger\NullLogger;
use Phpactor\Extension\Rpc\Handler\AbstractHandler;

class ClassCopyHandler extends AbstractHandler
{
    const NAME = 'copy_class';
    const PARAM_SOURCE_PATH = 'source_path';
    const PARAM_DEST_PATH = 'dest_path';


    /**
     * @var ClassCopy
     */
    private $classCopy;

    public function __construct(ClassCopy $classCopy)
    {
        $this->classCopy = $classCopy;
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function defaultParameters(): array
    {
        return [
            self::PARAM_SOURCE_PATH => null,
            self::PARAM_DEST_PATH => null,
        ];
    }

    public function handle(array $arguments)
    {
        $this->requireInput(TextInput::fromNameLabelAndDefault(
            self::PARAM_DEST_PATH,
            'Copy to: ',
            $arguments[self::PARAM_SOURCE_PATH]
        ));

        if ($this->hasMissingArguments($arguments)) {
            return $this->createInputCallback($arguments);
        }

        $this->classCopy->copy(new NullLogger(), $arguments[self::PARAM_SOURCE_PATH], $arguments[self::PARAM_DEST_PATH]);

        return OpenFileResponse::fromPath($arguments[self::PARAM_DEST_PATH]);
    }
}
