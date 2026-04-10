<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Brick\VarExporter\ExportException;
use Brick\VarExporter\VarExporter;
use Jield\ModuleManager\Listener\Exception\ConfigCannotBeCachedException;
use Webimpress\SafeWriter\FileWriter;

abstract class AbstractListener
{
    protected ListenerOptions $options;

    public function __construct(?ListenerOptions $options = null)
    {
        $options = $options ?: new ListenerOptions();
        $this->setOptions(options: $options);
    }

    public function getOptions(): ListenerOptions
    {
        return $this->options;
    }

    /**
     * @param ListenerOptions $options the value to be set
     * @return AbstractListener
     */
    public function setOptions(ListenerOptions $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Write a simple array of scalars to a file
     *
     * @param string $filePath
     * @param array $array
     * @return AbstractListener
     */
    protected function writeArrayToFile(string $filePath, array $array): static
    {
        try {
            $content = "<?php\n" . VarExporter::export(
                    var: $array,
                    options: VarExporter::ADD_RETURN | VarExporter::CLOSURE_SNAPSHOT_USES
                );
        } catch (ExportException $e) {
            throw ConfigCannotBeCachedException::fromExporterException(exportException: $e);
        }

        FileWriter::writeFile($filePath, $content);

        return $this;
    }
}
