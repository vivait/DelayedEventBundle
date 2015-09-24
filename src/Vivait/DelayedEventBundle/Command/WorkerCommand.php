<?php

namespace Vivait\DelayedEventBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Kernel;
use Vivait\DelayedEventBundle\Queue\Beanstalkd;
use Vivait\DelayedEventBundle\Queue\QueueInterface;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;
use Wrep\Daemonizable\Command\EndlessCommand;

class WorkerCommand extends EndlessCommand
{
	const DEFAULT_TIMEOUT = 0;
	const DEFAULT_WAIT_TIMEOUT = null;

    /**
     * @var Beanstalkd
     */
    private $queue;

	/**
	 * @var EventDispatcherInterface
	 */
	private $eventDispatcher;

	/**
	 * @var Kernel
	 */
	private $kernel;

	/**
	 * @param QueueInterface $queue
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param Kernel $kernel
	 */
	function __construct(QueueInterface $queue, EventDispatcherInterface $eventDispatcher, Kernel $kernel) {
		$this->queue = $queue;
        $this->eventDispatcher = $eventDispatcher;
		$this->kernel = $kernel;

		parent::__construct();
	}

	protected function configure()
	{
		$this
			->setName('vivait:delayed_event:worker')
			->setDescription('Runs the delayed event worker')
			->addOption('pause', 'p', InputOption::VALUE_OPTIONAL, 'Time to pause between iterations', self::DEFAULT_TIMEOUT)
			->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Maximum time to wait for a job - use with --run-once when debugging', self::DEFAULT_WAIT_TIMEOUT);
		;
	}

	/**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Wrep\Daemonizable\Exception\ShutdownEndlessCommandException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set pause amount
	    $pause = $input->getOption( 'pause' );
        $this->setTimeout( $pause );

        // Amount of time to wait for a job
        $wait_timeout = $input->getOption( 'timeout' );

        $job = $this->queue->get($wait_timeout);

	    if (!$job) {
		    $output->writeln(
			    sprintf("[%s] <error>Couldn't find job before timeout</error>", $this->getName())
		    );
		    return;
	    }

        $output->writeln(
            sprintf("[%s] <info>Performing jobs</info>", $this->getName())
        );

        $this->eventDispatcher->dispatch($job->getEventName(), $job->getEvent());

        // Delete it from the queue
        $this->queue->delete($job);

        $output->writeln(
            sprintf("[%s] <info>Job finished successfully and removed</info>", $this->getName())
        );
	}

	public function shutdown()
	{
		parent::shutdown();

		$this->kernel->shutdown();
		exit;
	}
}
