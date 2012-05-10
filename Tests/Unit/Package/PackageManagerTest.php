<?php
namespace TYPO3\FLOW3\Tests\Unit\Package;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\FLOW3\Package\PackageInterface;

/**
 * Testcase for the default package manager
 *
 */
class PackageManagerTest extends \TYPO3\FLOW3\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\FLOW3\Package\PackageManager
	 */
	protected $packageManager;

	/**
	 * Sets up this test case
	 *
	 */
	protected function setUp() {
		\vfsStreamWrapper::register();
		\vfsStreamWrapper::setRoot(new \vfsStreamDirectory('Test'));
		$mockBootstrap = $this->getMock('TYPO3\FLOW3\Core\Bootstrap', array(), array(), '', FALSE);
		$mockBootstrap->expects($this->any())->method('getSignalSlotDispatcher')->will($this->returnValue($this->getMock('TYPO3\FLOW3\SignalSlot\Dispatcher')));
		$this->packageManager = new \TYPO3\FLOW3\Package\PackageManager();

		mkdir('vfs://Test/Resources');
		$packageClassTemplateUri = 'vfs://Test/Resources/Package.php.tmpl';
		file_put_contents($packageClassTemplateUri, '<?php namespace {packageNamespace}; # The {packageKey} package');
		$this->packageManager->setPackageClassTemplateUri($packageClassTemplateUri);

		mkdir('vfs://Test/Packages/Application', 0700, TRUE);
		mkdir('vfs://Test/Configuration');

		$mockClassLoader = $this->getMock('TYPO3\FLOW3\Core\ClassLoader');

		$this->packageManager->injectClassLoader($mockClassLoader);
		$this->packageManager->initialize($mockBootstrap, 'vfs://Test/Packages/', 'vfs://Test/Configuration/PackageStates.php');
	}

	/**
	 * @test
	 */
	public function initializeUsesPackageStatesConfigurationForActivePackages() {
	}

	/**
	 * @test
	 */
	public function getPackageReturnsTheSpecifiedPackage() {
		$this->packageManager->createPackage('TYPO3.FLOW3');

		$package = $this->packageManager->getPackage('TYPO3.FLOW3');
		$this->assertInstanceOf('TYPO3\FLOW3\Package\PackageInterface', $package, 'The result of getPackage() was no valid package object.');
	}

	/**
	 * @test
	 * @expectedException \TYPO3\FLOW3\Package\Exception\UnknownPackageException
	 */
	public function getPackageThrowsExcpetionOnUnknownPackage() {
		$this->packageManager->getPackage('PrettyUnlikelyThatThisPackageExists');
	}

	/**
	 * @test
	 */
	public function getCaseSensitivePackageKeyReturnsTheUpperCamelCaseVersionOfAGivenPackageKeyIfThePackageIsRegistered() {
		$packageManager = $this->getAccessibleMock('TYPO3\FLOW3\Package\PackageManager', array('dummy'));
		$packageManager->_set('packageKeys', array('acme.testpackage' => 'Acme.TestPackage'));
		$this->assertEquals('Acme.TestPackage', $packageManager->getCaseSensitivePackageKey('acme.testpackage'));
	}

	/**
	 * @test
	 */
	public function scanAvailablePackagesTraversesThePackagesDirectoryAndRegistersPackagesItFinds() {
		$expectedPackageKeys = array(
			'TYPO3.FLOW3' . md5(uniqid(mt_rand(), TRUE)),
			'TYPO3.FLOW3.Test' . md5(uniqid(mt_rand(), TRUE)),
			'TYPO3.YetAnotherTestPackage' . md5(uniqid(mt_rand(), TRUE)),
			'RobertLemke.FLOW3.NothingElse' . md5(uniqid(mt_rand(), TRUE))
		);

		foreach ($expectedPackageKeys as $packageKey) {
			$packageNamespace = str_replace('.', '\\', $packageKey);
			$packagePath = 'vfs://Test/Packages/Application/' . str_replace('.', '/', $packageNamespace) . '/';
			$packageClassCode = '<?php
					namespace ' . $packageNamespace . ';
					class Package extends \TYPO3\FLOW3\Package\Package {}
			?>';

			mkdir($packagePath, 0770, TRUE);
			mkdir($packagePath . 'Classes');
			mkdir($packagePath . 'Meta');
			file_put_contents($packagePath . 'Classes/Package.php', $packageClassCode);
			file_put_contents($packagePath . 'Meta/Package.xml', '<xml>...</xml>');
		}

		$packageManager = $this->getAccessibleMock('TYPO3\FLOW3\Package\PackageManager', array('dummy'));
		$packageManager->_set('packagesBasePath', 'vfs://Test/Packages/');
		$packageManager->_set('packageStatesPathAndFilename', 'vfs://Test/Configuration/PackageStates.php');

		$packageManager->_set('packages', array());
		$packageManager->_call('scanAvailablePackages');

		$packageStates = require('vfs://Test/Configuration/PackageStates.php');
		$actualPackageKeys = array_keys($packageStates['packages']);
		$this->assertEquals(sort($expectedPackageKeys), sort($actualPackageKeys));
	}

	/**
	 * @test
	 * @expectedException TYPO3\FLOW3\Package\Exception\CorruptPackageException
	 */
	public function registerPackagesThrowsAnExceptionWhenItFindsACorruptPackage() {
		mkdir('vfs://Test/Packages/Application/TYPO3.YetAnotherTestPackage/Meta', 0770, TRUE);
		file_put_contents('vfs://Test/Packages/Application/TYPO3.YetAnotherTestPackage/Meta/Package.xml', '<xml>...</xml>');

		$packageStatesConfiguration['packages'] = array(
			'TYPO3.YetAnotherTestPackage' => array(
				'packagePath' => 'vfs://Test/Packages/Application/TYPO3.YetAnotherTestPackage/',
				'state' => 'active'
			)
		);

		$packageManager = $this->getAccessibleMock('TYPO3\FLOW3\Package\PackageManager', array('dummy'));
		$packageManager->_set('packagesBasePath', 'vfs://Test/Packages/');
		$packageManager->_set('packageStatesPathAndFilename', 'vfs://Test/Configuration/PackageStates.php');
		$packageManager->_set('packageStatesConfiguration', $packageStatesConfiguration);

		$packageManager->_call('registerPackages');
	}

	/**
	 * Data Provider returning valid package keys and the corresponding path
	 *
	 * @return array
	 */
	public function packageKeysAndPaths() {
		return array(
			array('TYPO3.YetAnotherTestPackage', 'vfs://Test/Packages/Application/TYPO3.YetAnotherTestPackage/'),
			array('RobertLemke.FLOW3.NothingElse', 'vfs://Test/Packages/Application/RobertLemke.FLOW3.NothingElse/')
		);
	}

	/**
	 * @test
	 * @dataProvider packageKeysAndPaths
	 */
	public function createPackageCreatesPackageFolderAndReturnsPackage($packageKey, $expectedPackagePath) {
		$actualPackage = $this->packageManager->createPackage($packageKey);
		$actualPackagePath = $actualPackage->getPackagePath();

		$this->assertEquals($expectedPackagePath, $actualPackagePath);
		$this->assertTrue(is_dir($actualPackagePath), 'Package path should exist after createPackage()');
		$this->assertEquals($packageKey, $actualPackage->getPackageKey());
		$this->assertTrue($this->packageManager->isPackageAvailable($packageKey));
	}

	/**
	 * @test
	 */
	public function createPackageWritesAPackageMetaFileUsingTheGivenMetaObject() {
		$metaData = new \TYPO3\FLOW3\Package\MetaData('Acme.YetAnotherTestPackage');
		$metaData->setTitle('Yet Another Test Package');

		$package = $this->packageManager->createPackage('Acme.YetAnotherTestPackage', $metaData);

		$actualPackageXml = simplexml_load_file($package->getMetaPath() . 'Package.xml');
		$this->assertEquals('Acme.YetAnotherTestPackage', (string)$actualPackageXml->key);
		$this->assertEquals('Yet Another Test Package', (string)$actualPackageXml->title);
	}

	/**
	 * Checks if createPackage() creates the folders for classes, configuration, documentation, resources and tests and
	 * the mandatory Package class.
	 *
	 * @test
	 */
	public function createPackageCreatesCommonFoldersAndThePackageClass() {
		$package = $this->packageManager->createPackage('Acme.YetAnotherTestPackage');
		$packagePath = $package->getPackagePath();

		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_CLASSES), "Classes directory was not created");
		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_CONFIGURATION), "Configuration directory was not created");
		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_DOCUMENTATION), "Documentation directory was not created");
		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_RESOURCES), "Resources directory was not created");
		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_TESTS_UNIT), "Tests/Unit directory was not created");
		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_TESTS_FUNCTIONAL), "Tests/Functional directory was not created");
		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_METADATA), "Metadata directory was not created");

		$actualPackageClassCode = file_get_contents($packagePath . PackageInterface::DIRECTORY_CLASSES . 'Package.php');
		$expectedPackageClassCode = '<?php namespace Acme\YetAnotherTestPackage; # The Acme.YetAnotherTestPackage package';
		$this->assertEquals($expectedPackageClassCode, $actualPackageClassCode);
	}

	/**
	 * Makes sure that an exception is thrown and no directory is created on passing invalid package keys.
	 *
	 * @test
	 */
	public function createPackageThrowsExceptionOnInvalidPackageKey() {
		try {
			$this->packageManager->createPackage('Invalid_PackageKey');
		} catch(\TYPO3\FLOW3\Package\Exception\InvalidPackageKeyException $exception) {
		}
		$this->assertFalse(is_dir('vfs://Test/Packages/Application/Invalid_PackageKey'), 'Package folder with invalid package key was created');
	}

	/**
	 * Makes sure that duplicate package keys are detected.
	 *
	 * @test
	 * @expectedException TYPO3\FLOW3\Package\Exception\PackageKeyAlreadyExistsException
	 */
	public function createPackageThrowsExceptionForExistingPackageKey() {
		$this->packageManager->createPackage('Acme.YetAnotherTestPackage');
		$this->packageManager->createPackage('Acme.YetAnotherTestPackage');
	}

	/**
	 * @test
	 */
	public function createPackageActivatesTheNewlyCreatedPackage() {
		$this->packageManager->createPackage('Acme.YetAnotherTestPackage');
		$this->assertTrue($this->packageManager->isPackageActive('Acme.YetAnotherTestPackage'));
	}

	/**
	 * @test
	 */
	public function activatePackageAndDeactivatePackageActivateAndDeactivateTheGivenPackage() {
		$packageKey = 'Acme.YetAnotherTestPackage';

		$this->packageManager->createPackage($packageKey);

		$this->packageManager->deactivatePackage($packageKey);
		$this->assertFalse($this->packageManager->isPackageActive($packageKey));

		$this->packageManager->activatePackage($packageKey);
		$this->assertTrue($this->packageManager->isPackageActive($packageKey));
	}

	/**
	 * @test
	 * @expectedException \TYPO3\FLOW3\Package\Exception\ProtectedPackageKeyException
	 */
	public function deactivatePackageThrowsAnExceptionIfPackageIsProtected() {
		$package = $this->packageManager->createPackage('Acme.YetAnotherTestPackage');
		$package->setProtected(TRUE);
		$this->packageManager->deactivatePackage('Acme.YetAnotherTestPackage');
	}

	/**
	 * @test
	 * @expectedException \TYPO3\FLOW3\Package\Exception\UnknownPackageException
	 */
	public function deletePackageThrowsErrorIfPackageIsNotAvailable() {
		$this->packageManager->deletePackage('PrettyUnlikelyThatThisPackageExists');
	}

	/**
	 * @test
	 * @expectedException \TYPO3\FLOW3\Package\Exception\ProtectedPackageKeyException
	 */
	public function deletePackageThrowsAnExceptionIfPackageIsProtected() {
		$package = $this->packageManager->createPackage('Acme.YetAnotherTestPackage');
		$package->setProtected(TRUE);
		$this->packageManager->deletePackage('Acme.YetAnotherTestPackage');
	}

	/**
	 * @test
	 */
	public function deletePackageRemovesPackageFromAvailableAndActivePackagesAndDeletesThePackageDirectory() {
		$package = $this->packageManager->createPackage('Acme.YetAnotherTestPackage');
		$packagePath = $package->getPackagePath();

		$this->assertTrue(is_dir($packagePath . PackageInterface::DIRECTORY_METADATA));
		$this->assertTrue($this->packageManager->isPackageActive('Acme.YetAnotherTestPackage'));
		$this->assertTrue($this->packageManager->isPackageAvailable('Acme.YetAnotherTestPackage'));

		$this->packageManager->deletePackage('Acme.YetAnotherTestPackage');

		$this->assertFalse(is_dir($packagePath . PackageInterface::DIRECTORY_METADATA));
		$this->assertFalse($this->packageManager->isPackageActive('Acme.YetAnotherTestPackage'));
		$this->assertFalse($this->packageManager->isPackageAvailable('Acme.YetAnotherTestPackage'));
	}
}
?>