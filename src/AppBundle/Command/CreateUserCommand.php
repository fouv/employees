<?php

namespace AppBundle\Command;

use AppBundle\Entity\Employee;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateUserCommand extends ContainerAwareCommand
{
    const INTERVAL = 1000;

    /** @var  EntityManager */
    private $em;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->em = $this->getContainer()->get('doctrine')->getManager();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:import') //
            ->setDescription('update enterprise directory')
            ->addOption('force', InputOption::VALUE_NONE);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $employeeRepo = $this->em->getRepository("AppBundle:Employee");
        $file = $this->getContainer()->get("kernel")->getRootDir() . "/../src/AppBundle/Data/employees.csv";

        $fileHandle = new \SplFileObject($file, 'r');
        $fileHandle->seek(PHP_INT_MAX);
        $totalRows = $fileHandle->key() + 1;
        $fileHandle->seek(0);
        $progress = new ProgressBar($output, $totalRows / self::INTERVAL);
        $output->writeln("Start working with file : " . $file);

        $totalImport = 0;
        $totalIgnored = 0;

        while (!$fileHandle->eof()){
            $row = $fileHandle->fgetcsv();
            $id = $row[0];
            if (!$employeeRepo->find($id)){
                if ($input->getOption('force')) {
                    $employee = new Employee();
                    $employee->setId($id);
                    $employee->setBirthday(new \DateTime($row[1]));
                    $employee->setLastname($row[2]);
                    $employee->setName($row[3]);
                    $employee->setGender($row[4]);
                    $employee->setInscription(new \DateTime($row[5]));
                    $this->em->persist($employee);
                }
                $totalImport++;
            } else {
                $totalIgnored++;
            }
            if (($totalImport + $totalIgnored) % self::INTERVAL === 0){
                $progress->advance();
                if ($input->getOption('force')) {
                    $this->em->flush();
                }
            }
        }
        if ($input->getOption('force')) {
            $this->em->flush();
        }
        $progress->finish();
        $option = "";
        if ($input->getOption('force')) {
            $option = "(simulated)";
        }
        $output->writeln("Imported $option : " . $totalImport);
        $output->writeln("Ignored  : " . $totalIgnored);
    }
}