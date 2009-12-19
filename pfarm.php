<?php
namespace PEAR\PearFarm;

require_once('PEAR/PackageFileManager2.php');
\PEAR::setErrorHandling(PEAR_ERROR_DIE);

interface Task {
	public function run($args);
	public function showHelp();
	public function getAliases();
	public function getName();
	public function getDescription();
}

class TaskArgumentException extends \Exception {
	
}

class PlantTask implements Task {
	public function run($args) {
		if(!isset($args[2])) {
			throw new TaskArgumentException("You must specify a package name.\n");
		}
		//TODO: check if there is already a directory with that name
		//TODO: what should we do if we don't have write permissions?
		//TODO: validate package name
		$packageName = $args[2];
		mkdir($packageName);
		mkdir($packageName . DIRECTORY_SEPARATOR . 'src');
		mkdir($packageName . DIRECTORY_SEPARATOR . 'data');
		mkdir($packageName . DIRECTORY_SEPARATOR . 'tests');
		mkdir($packageName . DIRECTORY_SEPARATOR . 'doc');
		mkdir($packageName . DIRECTORY_SEPARATOR . 'www');
		mkdir($packageName . DIRECTORY_SEPARATOR . 'examples');

		// create default class
		// TODO: add doc block to class
		file_put_contents($packageName . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . ucfirst($packageName) . '.php', "<?php\nclass " . ucfirst($packageName) . " {\n\n\n}");

		//TODO: generate package spesification file

	}
	public function showHelp() {
		echo "TODO: Print some help.\n";
	}
	public function getName() {
		return "plant";
	}
	public function getAliases() {
		return array("p", "pl");
	}
	public function getDescription() {
		return "creates the package";
	}
}

class CollectTask implements Task {
	public function run($args) {

	}
	public function showHelp() {

	}
	public function getName() {
		return "collect";
	}
	public function getAliases() {
		return array();
	}
	public function getDescription() {
		return "builds the package";
	}
}

class TryTask implements Task {
	public function run($args) {

	}
	public function showHelp() {

	}
	public function getName() {
		return "try";
	}
	public function getAliases() {
		return array();
	}
	public function getDescription() {
		return "installs the package for testing purposes";
	}
}

class DeliverTask implements Task {
	public function run($args) {

	}
	public function showHelp() {

	}
	public function getName() {
		return "deliver";
	}
	public function getAliases() {
		return array();
	}
	public function getDescription() {
		return "sends the package to pearfarm.org";
	}
}

class PFarm {
	private $args;
	private $tasks;
	private $verbs;

	public function __construct(array $args, $registrations) {
		$this->args = $args;
		$registrations($this);
	}
	public function run() {
		if(!isset($this->args[1]) || !isset($this->verbs[$this->args[1]])) {
			$this->showHelp();
			//TODO: define exit codes
			exit(-1);
		}
		$task = $this->verbs[$this->args[1]];
		try {
			$task->run($this->args);
			exit();
		} catch(TaskArgumentException $ex) {
			echo $ex->getMessage()."\n";
			$task->showHelp();
			//TODO: define exit codes
			exit(-2);
		}
	}
	public function showHelp() {
		echo("usage: pfarm COMMAND [ARGS]\n\nThe pfarm commands are:\n");
		foreach($this->tasks as $task) {
			$aliases = implode(", ", $task->getAliases());
			if(!empty($aliases)) {
				$aliases = " (".$aliases.")";
			}
			echo str_pad($task->getName().$aliases, 20, " ", STR_PAD_LEFT)."\t".$task->getDescription()."\n";
		}
		echo("\n");
	}
	public function register(Task $task) {
		$this->tasks[$task->getName()] = $task;
		$this->verbs[$task->getName()] = $task;
		foreach($task->getAliases() as $verb) {
			$this->verbs[$verb] = $task;
		}
	}
}

$pfarm = new PFarm($argv, function($pfarm) {
	$pfarm->register(new PlantTask());
	$pfarm->register(new CollectTask());
	$pfarm->register(new TryTask());
	$pfarm->register(new DeliverTask());
});
$pfarm->run();

die();

/*

THIS IS AN EXAMPLE OF HOW TO GENERATE THE XML PACKAGE FILE
$pfm = new PEAR_PackageFileManager2();

//TODO: Define defaults for most of these parameters and decide which one we will require from the user.
$e = $pfm->setOptions(
array(
'baseinstalldir' => '',
'packagedirectory' => '.',
//TODO: find a good way to add ignore files for .svn or .git, etc.
'filelistgenerator' => 'file', //this should be file, because other options are svn or cvs, but I think it doesn't really make sense
'ignore' => array(),
'installexceptions' => array(),
'dir_roles' => array(),
'exceptions' => array()
)
); // same for the license
$pfm->setPackage('MyPackage');
$pfm->setSummary('this is my package');
$pfm->setDescription('this is my package description');

//TODO: By default we should put pearfarm channel here
$pfm->setChannel('pear.php.net');

//what's api version?????
$pfm->setAPIVersion('1.0.0');
$pfm->setReleaseVersion('1.2.1');
$pfm->setReleaseStability('stable');

//again api???
$pfm->setAPIStability('stable');
$pfm->setNotes("We've implemented many new and exciting features");

//should we care about this?
$pfm->setPackageType('php'); // this is a PEAR-style php script package
$pfm->setOSInstallCondition('windows');
$pfm->setPhpDep('4.2.0');
$pfm->setPearinstallerDep('1.4.0a12');
$pfm->addMaintainer('lead', 'cellog', 'Greg Beaver', 'cellog@php.net');
$pfm->setLicense('PHP License', 'http://www.php.net/license');
$pfm->generateContents(); // create the <contents> tag
$pfm->debugPackageFile(); //show the xml
$pfm->writePackageFile(); //write the xml
//*/