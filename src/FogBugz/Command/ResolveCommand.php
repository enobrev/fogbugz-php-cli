<?php
namespace FogBugz\Command;

use There4\FogBugz\ApiError;
use FogBugz\Cli\AuthCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResolveCommand extends AuthCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('resolve')
            ->setDescription('Resolve a case')
            ->addArgument('case', InputArgument::OPTIONAL, 'Case number, will use current active if omitted.')
            ->addArgument('note', InputArgument::OPTIONAL, 'Message to leave on the case.')
            ->requireAuth(true);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->app = $this->getApplication();
        $dialog    = new DialogHelper();
        $case      = $input->getArgument('case');
        $note      = $input->getArgument('note');

        // fb note "string message" and so we swap case and note
        if (!is_numeric($case)) {
            $note = $case;
            $case = $this->app->getCurrent();
            if (empty($case)) {
                $case = $dialog->ask($output, "Enter a case number:");
            } else {
                $output->writeln(
                    sprintf("<notice>Resolving current Case %d.</notice>", $case),
                    $this->app->outputFormat
                );
            }
        }

        $category = $this->app->fogbugz->search(
            array(
                'q'    => (int) $case,
                'cols' => 'ixCategory'
            )
        );
        $category = (int) $category->cases->case->ixCategory;
        $statuses = $this->app->fogbugz->listStatuses(
            array(
                'fResolved'  => 1,
                'ixCategory' => (int) $category
            )
        );
        foreach ($statuses->statuses->status as $status) {
            $output->writeln(
                sprintf(
                    "  <info>[%d]</info> %s",
                    $status->ixStatus,
                    $status->sStatus
                ),
                $this->app->outputFormat
            );
        }

        $status = "";
        while (!is_numeric($status)) {
            $status = $dialog->ask($output, 'Enter the status from the list above: ');
        }

        $activePeople = $this->app->fogbugz->listPeople(
            array(
                'fIncludeActive' => 1
            )
        );
        $virtualPeople = $this->app->fogbugz->listPeople(
            array(
                'fIncludeVirtual' => 1
            )
        );

        $output->writeln('<alert>Active Users</alert>');
        $i = 1;
        foreach (array($activePeople, $virtualPeople) as $people) {
            foreach ($people->people->person as $person) {
                $output->writeln(
                    sprintf(
                        "  <info>[%s%d]</info> %s",
                        strlen($person->ixPerson) - 1 ? '' : ' ',
                        $person->ixPerson,
                        $person->sFullName
                    ),
                    $this->app->outputFormat
                );
            }
            $i && $output->writeln('<alert>Virtual Users</alert>');
            $i--;
        }

        // TODO: validate the `assignedto` var.
        $assignedto = $dialog->ask($output, "Who should the case be assigned to: ");

        if (empty($note)) {
            $note = $dialog->ask(
                $output,
                sprintf("Please supply a note for Case %d (optional):\n", $case)
            );
        }

        $request = array(
            'ixStatus'           => $status,
            'ixBug'              => $case,
            'ixPersonAssignedTo' => $assignedto,
            'sEvent'             => empty($note) ? '' : $note
        );

        try {
            $this->app->fogbugz->resolve($request);
            printf(
                "Resolved case %s\n",
                $case
            );
        } catch (ApiError $e) {
            $output->writeln(
                sprintf("<error>%s</error>", $e->getMessage()),
                $this->app->outputFormat
            );
            exit(1);
        }
    }
}

/* End of file ResolveCommand.php */
