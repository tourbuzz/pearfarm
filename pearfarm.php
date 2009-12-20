<?php

class PEARFarm_Specification
{
    const LICENSE_MIT           = 'mit';

    // core settings
    protected $name             = NULL;
    protected $channel          = NULL;
    protected $summary          = NULL;
    protected $description      = NULL;
    protected $releaseVersion   = NULL;
    protected $releaseStability = NULL;
    protected $apiVersion       = NULL;
    protected $apiStability     = NULL;
    protected $license          = NULL;
    protected $notes            = NULL;
    protected $maintainers      = array();

    // dependencies
    protected $dependsOnPHPVersionMin           = '5.0.0';
    protected $dependsOnPHPVersionMax           = NULL;
    protected $dependsOnPHPVersionExclude       = array();

    protected $dependsOnPearInstallerVersionMin         = '1.4.0';
    protected $dependsOnPearInstallerVersionMax         = NULL;
    protected $dependsOnPearInstallerVersionRecommended = NULL;
    protected $dependsOnPearInstallerVersionExclude     = array();

    protected $dependsOnExtensions              = array();
    protected $dependsOnPEARPackages            = array();

    // package contents
    protected $files            = array();

    private static $licenseData = array(
        self::LICENSE_MIT => array('name' => 'MIT', 'uri' => 'http://www.opensource.org/licenses/mit-license.html')
    );

    public function __construct($options = array())
    {
    }

    public function addFile(PEARFarm_Specification_File $f)
    {
        $this->files[$f->getFilePath()] = $f;
    }

    public function addFilesSimple($files = array(), $role = 'php', $options = array())
    {
        foreach ($files as $f) {
            $this->addFile( new PEARFarm_Specification_File($f, $role, $options) );
        }
        return $this;
    }

    public function addMaintainer($type, $name, $user, $email, $active = true)
    {
        $this->maintainers[$type][] = array('name' => $name, 'user' => $user, 'email' => $email, 'active' => $active);
        return $this;
    }

    public function setDependsOnPHPVersion($min, $max = NULL, $exclude = array())
    {
        $this->dependsOnPHPVersionMin = $min;
        $this->dependsOnPHPVersionMax = $max;
        $this->dependsOnPHPVersionExclude = $exclude;

        return $this;
    }

    public function setDependsOnPearInstallerVersion($min, $max = NULL, $recommended = NULL, $exclude = array())
    {
        $this->dependsOnPearInstallerVersionMin = $min;
        $this->dependsOnPearInstallerVersionMax = $max;
        $this->dependsOnPearInstallerVersionRecommended = $recommended;
        $this->dependsOnPearInstallerVersionExclude = $exclude;

        return $this;
    }

    public function addGitFiles($exclude = array())
    {
        $result = NULL;
        $output = array();
        $lastLine = exec('git ls-files', $output, $result);
        if ($result != 0) throw( new Exception("Error ($result) running git ls-files: " . join("\n", $output)) );

        $this->addFilesSimple($output);

        return $this;
    }

    public static function newSpec($options = array())
    {
        return new PEARFarm_Specification($options);
    }

    public function getLicense()
    {
        if (is_array($this->license))
        {
            return $this->license;
        }
        return self::$licenseData[$this->license];
    }

    public function __call($name, $value)
    {
        switch (substr($name, 0, 3)) {
            case 'set':
                $varName = substr($name, 3);
                $varName[0] = strtolower($varName[0]);
                //if (isset($this->$varName)) // not sure how to test (w/o reflection) to make sure $this->$varName exists.
                //{
                    $this->$varName = $value[0];
                    return $this;
                //}
                break;
            case 'get':
                $varName = substr($name, 3);
                $varName[0] = strtolower($varName[0]);
                return $this->$varName;
                break;
        }
        throw new Exception("Function $name does not exist.");
    }

    public function writePackageFile()
    {
        // http://pear.php.net/manual/en/guide.developers.package2.php
        // unfortunately, order matters in package.xml, so don't mess with it!
        $xml = simplexml_load_string('<package/>', 'SuperSimpleXMLElement');
        $xml->addAttribute('version', '2.0');
        foreach (array('name', 'channel', 'summary', 'description') as $property) {
            $xml->addTextNode($property, htmlentities($this->$property));
        }

        // need to sort by leads, developers, contributors, helpers
        foreach ($this->maintainers as $type => $maintainers) {
            foreach ($maintainers as $maintainer) {
                $typeNode = $xml->addChild($type);
                $typeNode->addChild('name', $maintainer['name']);
                $typeNode->addChild('user', $maintainer['user']);
                $typeNode->addChild('email', $maintainer['email']);
                $typeNode->addChild('active', $maintainer['active'] ? 'yes' : 'no');
            }
        }

        $now = time();
        $xml->addTextNode('date', date('Y-m-d', $now));
        $xml->addTextNode('time', date('H:i:s', $now));

        $version = $xml->addChild('version');
        $version->addTextNode('release', $this->releaseVersion);
        $version->addTextNode('api', $this->apiVersion);

        $stability = $xml->addChild('stability');
        $stability->addTextNode('release', $this->releaseStability);
        $stability->addTextNode('api', $this->apiStability);

        $licenseData = $this->getLicense();
        $license = $xml->addTextNode('license', $licenseData['name']);
        $license->addAttribute('uri', $licenseData['uri']);

        foreach (array('notes') as $property) {
            $xml->addTextNode($property, htmlentities($this->$property));
        }

        $contentsNode = $xml->addChild('contents');
        // baseinstalldir = "name" of package --- prevents conflicts i suppose
        $rootDirObj = new PEARFarm_Specification_Dir('.', array(PEARFarm_Specification_Dir::BASEINSTALLDIR => $this->name));

        // build all dir & file blocks
        ksort($this->files);
        $dirs = array('.' => $rootDirObj);    // dirPath => object PEARFarm_Specification_Dir
        foreach ($this->files as $filePath => $fileObj) {
            $fileDirPath = dirname($filePath);

            // make sure all dirs up to this point are represented
            $allDirs = explode(DIRECTORY_SEPARATOR, ltrim($fileDirPath, DIRECTORY_SEPARATOR));
            $dirPath = NULL;
            $lastDirObj = $rootDirObj;
            foreach ($allDirs as $dir) {
                $dirPath .= $dir;
                if (isset($dirs[$dirPath])) continue;

                // create directory
                $dirObj = new PEARFarm_Specification_Dir($dirPath);
                $dirs[$dirPath] = $dirObj;
                // wire directory into hierarchy
                $lastDirObj->addItem($dirObj);
                $lastDirObj = $dirObj;

                $dirPath .= DIRECTORY_SEPARATOR;
                $depth = 1;
            }
            // add files
            $lastDirObj->addItem($fileObj);
        }
        $rootDirObj->addXMLAsChild($contentsNode);

        // deps
        $depsNode = $xml->addChild('dependencies');
        // required
        $reqNode = $depsNode->addChild('required');
        // php
        $phpNode = $reqNode->addChild('php');
        $phpNode->addChild('min', $this->dependsOnPHPVersionMin);
        if ($this->dependsOnPHPVersionMax !== NULL)
        {
            $phpNode->addChild('max', $this->dependsOnPHPVersionMax);
        }
        foreach ($this->dependsOnPHPVersionExclude as $excludeVersion) {
            $phpNode->addChild('exclude', $excludeVersion);
        }
        // pear installer
        $pearInstallerNode = $reqNode->addChild('pearinstaller');
        $pearInstallerNode->addChild('min', $this->dependsOnPearInstallerVersionMin);
        if ($this->dependsOnPearInstallerVersionMax !== NULL)
        {
            $pearInstallerNode->addChild('max', $this->dependsOnPearInstallerVersionMax);
        }
        if ($this->dependsOnPearInstallerVersionMax !== NULL)
        {
            $pearInstallerNode->addChild('max', $this->dependsOnPearInstallerVersionMax);
        }
        if ($this->dependsOnPearInstallerVersionRecommended !== NULL)
        {
            $pearInstallerNode->addChild('recommended', $this->dependsOnPearInstallerVersionRecommended);
        }
        foreach ($this->dependsOnPearInstallerVersionExclude as $excludeVersion) {
            $pearInstallerNode->addChild('exclude', $excludeVersion);
        }

        // optional deps
        $optNode = $depsNode->addChild('optional');

        // ????
        $phpReleaseNode = $xml->addChild('phprelease');

        file_put_contents('package.xml', $xml->asXML());
    }
}

abstract class PEARFarm_Specification_Item
{
    protected $nodeName;
    protected $requiredAttributes;
    protected $attributes;

    public function __construct($nodeName, $requiredAttributes)
    {
        $this->nodeName = $nodeName;
        $this->requiredAttributes = $requiredAttributes;
    }

    public function setAttribute($k, $v)
    {
        $this->attributes[$k] = $v;
        return $this;
    }

    public function setAttributes($attrs)
    {
        foreach ($attrs as $k => $v) {
            $this->setAttribute($k, $v);
        }
        return $this;
    }

    public function addXMLAsChild($parentNode)
    {
        $node = $parentNode->addChild($this->nodeName);
        foreach ($this->attributes as $k => $v) {
            if ($v === NULL and in_array($k, $this->requiredAttributes)) throw new Exception("Attribute {$k} is required for {get_class($this)}.");
            if ($v === NULL) continue;  // skip optional attributes
            $node->addAttribute($k, $v);
        }
        return $node;
    }
}
class PEARFarm_Specification_Dir extends PEARFarm_Specification_Item
{
    const BASEINSTALLDIR        = 'baseinstalldir';

    // relative path to dir
    private $dirPath;
    private $items; // all items contained in this dir

    public function __construct($dirPath, $options = array())
    {
        parent::__construct('dir', array('name'));

        // internal stuff
        $this->dirPath = $dirPath;

        // required attrs
        // The "root" dir . is called / in pear
        $baseDirName = basename($dirPath);
        if ($baseDirName === '.')
        {
            $baseDirName = '/';
        }
        $this->setAttribute('name', $baseDirName);
        
        // optional attrs
        $options = array_merge($options, array(
                    self::BASEINSTALLDIR => NULL,
                    )
                );
        $this->setAttributes($options);
    }

    public function addItem($item)
    {
        if (!($item instanceof PEARFarm_Specification_Dir) and !($item instanceof PEARFarm_Specification_File)) throw new Exception("PEARFarm_Specification_Dir can only contain PEARFarm_Specification_Dir and PEARFarm_Specification_File objects.");
        $this->items[] = $item;
    }

    public function addXMLAsChild($parentNode)
    {
        $node = parent::addXMLAsChild($parentNode);
        foreach ($this->items as $item) {
            $childNode = $item->addXMLAsChild($node);
        }
        return $node;
    }
}

class PEARFarm_Specification_File extends PEARFarm_Specification_Item
{
    const BASEINSTALLDIR        = 'baseinstalldir';
    const MD5SUM                = 'md5sum';

    // relative path to File
    private $filePath;

    public function __construct($filePath, $role = 'php', $options = array())
    {
        parent::__construct('file', array('name', 'role'));

        // internal stuff
        $this->filePath = $filePath;

        // required attrs
        $this->setAttribute('name', basename($filePath));
        $this->setAttribute('role', $role);

        // optional attrs
        $options = array_merge($options, array(
                    self::BASEINSTALLDIR => NULL,
                    self::MD5SUM => NULL
                    )
                );
        $this->setAttributes($options);

        if (file_exists($filePath))
        {
            $this->setAttribute(self::MD5SUM, md5_file($filePath));
        }
    }

    public function getFilePath()
    {
        return $this->filePath;
    }
}

class SuperSimpleXMLElement extends SimpleXMLElement
{
    public function addTextNode($entityName, $text)
    {
        $newNode = $this->addChild($entityName);
        $newNode[0] = $text;
        return $newNode;
    }
}
