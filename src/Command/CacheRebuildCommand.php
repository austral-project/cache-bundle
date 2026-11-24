<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Command;

use Austral\CacheBundle\Event\CacheRebuildEvent;
use Austral\ToolsBundle\Command\Base\Command;
use Austral\ToolsBundle\Command\Exception\CommandException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clears the Austral caches and lets the bundles rebuild theirs (after a deployment, or a change made
 * outside Doctrine).
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class CacheRebuildCommand extends Command
{
  /**
   * @var string
   */
  protected string $titleCommande = 'Rebuild the Austral caches';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void
  {
    $this
      ->setDefinition([
        new InputOption('result', null, InputOption::VALUE_NONE, 'Only the results of Doctrine queries'),
        new InputOption('render', null, InputOption::VALUE_NONE, 'Only the HTML renders'),
      ])
      ->setDescription($this->titleCommande)
      ->setHelp(
        <<<'EOF'
          The <info>%command.name%</info> command clears the Austral caches and rebuilds them.

            <info>php %command.full_name%</info>            both caches
            <info>php %command.full_name% --result</info>   the results of Doctrine queries
            <info>php %command.full_name% --render</info>   the HTML renders
          EOF
      )
    ;
  }

  /**
   * @param InputInterface  $input
   * @param OutputInterface $output
   *
   * @throws CommandException
   */
  protected function executeCommand(InputInterface $input, OutputInterface $output): void
  {
    if (null === $this->eventDispatcher)
    {
      throw new CommandException('The event dispatcher is not set.');
    }
    $result = (bool) $input->getOption('result');
    $render = (bool) $input->getOption('render');
    if (!$result && !$render)
    {
      $result = $render = true;
    }

    $this->eventDispatcher->dispatch(new CacheRebuildEvent($result, $render), CacheRebuildEvent::EVENT_REBUILD);
    $this->viewMessage('Austral caches rebuilt', self::MESSAGE_TYPE_SUCCESS);
  }
}
