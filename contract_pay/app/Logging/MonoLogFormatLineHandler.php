<?php
namespace App\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Formatter\LineFormatter;
class MonoLogFormatLineHandler extends RotatingFileHandler implements HandlerInterface {
  const MICROTIME = 'Y-m-d H:i:s.u';
  public function __construct($filename) {
    parent::__construct($filename);
    
  }
  public function handle(array $record) {
    if (!$this->isHandling($record)) {
      return false;
    }
    $record = $this->processRecord($record);
    $this->setFormatter(new LineFormatter(null, self::MICROTIME));
    $record['formatted'] = $this->getFormatter()->format($record);
    $this->write($record);
    return false === $this->bubble;
  }

  public function __invoke(array $record) {
    
  }
}
