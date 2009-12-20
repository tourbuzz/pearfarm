<?php

$spec = PEARFarm_Specification::newSpec()
            ->setName('iphp')
            ->setChannel('pear.nimblize.com')
            ->setSummary('PHP Shell')
            ->setDescription('An interactive PHP Shell (or Console, or REPL).')
            ->setReleaseVersion('1.0.0')
            ->setReleaseStability('stable')
            ->setApiVersion('1.0.0')
            ->setApiStability('stable')
            ->setLicense(PEARFarm_Specification::LICENSE_MIT)
            ->setNotes('boo notes should not be required')
            ->setDependsOnPHPVersion('5.0.0')
            ->setDependsOnPearInstallerVersion('1.4.0')
            ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
            ->addGitFiles()
            ->addFilesSimple(array('a/b/c.php'));
