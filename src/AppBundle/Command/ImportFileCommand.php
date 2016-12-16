<?php
/**
 * Created by PhpStorm.
 * User: medrupaloscil
 * Date: 05/12/2016
 * Time: 10:46
 */

namespace AppBundle\Command;

use AppBundle\Entity\Card;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Validator\Constraints\Date;

class ImportFileCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('import:file')
            ->setDescription('Import file in BDD')
            ->addArgument('file', InputArgument::REQUIRED, 'The file path')
            ->addArgument('model', InputArgument::REQUIRED, 'The Model name')
            ->setHelp("This command allows you to import file into the bdd");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        $file = __DIR__."/../../../".$input->getArgument('file');
        $model = $input->getArgument('model');

        $sql = "LOAD DATA INFILE '$file' INTO TABLE ";
        if ($model == "card") {
            $sql .= "navigo.card LINES TERMINATED BY '\n' (card_id, @valid) set valid=:date;";
            $stmt = $em->getConnection()->prepare($sql);
            $stmt->execute(array("date" => date('Y-m-d', strtotime('+1 year')) ));
        } else if ($model == "user") {
            $sql .= "navigo.user FIELDS TERMINATED BY ' ' LINES TERMINATED BY '\n' (firstname, lastname, @mail, @roles) set mail=CONCAT(lastname,'.',firstname,FLOOR(RAND() * 999999),'@gmail.com'), roles='a:1:{i:0;s:10:\"ROLE_USERS\";}';";
            $stmt = $em->getConnection()->prepare($sql);
            $stmt->execute();
        }
    }
}