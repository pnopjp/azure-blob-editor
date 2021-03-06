<?php
/**
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * PHP version 5
 *
 * @category  Microsoft
 * @package   WindowsAzure\Blob
 * @version   1.0
 * @author    <sakurai@pnop.co.jp>
 * @copyright 2013 pnop.inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link      http://www.pnop.co.jp/
 */

/* change your Windows Azure SDK for PHP path if you want */
require_once 'vendor/autoload.php';

/* change blob count of list if you want  */
$blobPerPage = 20;

/* upload max_file_size */
$maxUploadFileSize = 1024 * 1024 *1024;

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Common\ServiceException;

use WindowsAzure\Blob\BlobRestProxy;

use WindowsAzure\Blob\Models\CommitBlobBlocksOptions;
use WindowsAzure\Blob\Models\CreateContainerOptions;
use WindowsAzure\Blob\Models\PublicAccessType;

use WindowsAzure\Blob\Models\ListContainersResult;
use WindowsAzure\Blob\Models\Container;
use WindowsAzure\Blob\Models\ContainerProperties;
//use WindowsAzure\Blob\Models\GetContainerAclResult;

use WindowsAzure\Blob\Models\Blob;
use WindowsAzure\Blob\Models\Block;
use WindowsAzure\Blob\Models\BlobBlockType;

use WindowsAzure\Common\Internal\Utilities;

class MyBlobRestProxy extends BlobRestProxy
{
    private function _getCopyBlobSourceName($containerName, $blobName, $options)
    {
        $sourceName  = 'https://' . $this->getAccountName() . ".blob.core.windows.net";
        $sourceName .= '/' . $containerName . '/' . $blobName;

        if (!is_null($options->getSourceSnapshot())) {
            $sourceName .= '?snapshot=' . $options->getSourceSnapshot();
        }

        return $sourceName;
    }
}

class MyServicesBuilder extends ServicesBuilder
{
    public function createBlobService($connectionString)
    {
        $settings = StorageServiceSettings::createFromConnectionString(
            $connectionString
        );

        $httpClient    = $this->httpClient();
        $serializer    = $this->serializer();
        $uri           = Utilities::tryAddUrlScheme(
            $settings->getBlobEndpointUri()
        );

        $blobWrapper = new MyBlobRestProxy(
            $httpClient,
            $uri,
            $settings->getName(),
            $serializer
        );

        // Adding headers filter
        $headers = array();

        $headers[Resources::X_MS_VERSION] = Resources::STORAGE_API_LATEST_VERSION;

        $headersFilter = new HeadersFilter($headers);
        $blobWrapper   = $blobWrapper->withFilter($headersFilter);

        // Adding date filter
        $dateFilter  = new DateFilter();
        $blobWrapper = $blobWrapper->withFilter($dateFilter);

        $authFilter = new AuthenticationFilter(
            $this->blobAuthenticationScheme(
                $settings->getName(),
                $settings->getKey()
            )
        );

        $blobWrapper = $blobWrapper->withFilter($authFilter);

        return $blobWrapper;
    }
}

/**
 * This class provides Azure Blob interface
 *
 * @category  Microsoft
 * @package   WindowsAzure\Blob
 * @author    <sakurai@pnop.co.jp>
 * @copyright 2013 pnop.inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @version   Release: @package_version@
 * @link      http://www.pnop.co.jp/
 */
class AzureBlob
{
  /**
   * @var string : account Name
   */
  private $accountName;

  /**
   * @var string : access key
   */
  private $accessKey;

  /**
   * @var object : Blob Service object
   */
  private $blobService;

  /**
   * @var int : Error Code
   */
  public $errorCode;

  /**
   * @var string : Error Message
   */
  public $errorMessage;

  /**
   * @var int : Blob List Count
   */
  private $blobCount;

  /**
   * constructor
   *
   * @param string $accountName account name for access Azure storage
   * @param string $accessKey key for access Azure storagfe
   * @param string $protocol protocol name for access blob storage(http or https)
   */
  function __construct($accountName, $accessKey, $protocol='http')
  {
    /* connection strings */
    $connectionString = 'DefaultEndpointsProtocol=' . $protocol . 
                        ';AccountName=' . $accountName . 
                        ';AccountKey=' . $accessKey;

    /* connection establish */
    $this->blobService = MyServicesBuilder::getInstance()->createBlobService($connectionString);

    $this->accountName = $accountName;
    $this->accessKey = $accessKey;
  }

  /**
   * destructor
   */
  function __destruct() {
  }

  /**
   * Get Container list
   *
   * @return array
   */
  public function getContainerList()
  {
    $list = array();

    try {
      $list = $this->blobService->listContainers();
    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
    }

    return $list;
  }

  /**
   * Get Container ACL
   *
   * @param string containerName container name
   * @return ContainerACL
   */
/*
  public function getContainerAcl($containerName)
  {
    try {
      return $this->blobService->getContainerAcl($containerName);
    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
      return false;
    }
  }
*/

  /**
   * Create Container
   *
   * @param string containerName container name
   * @param int accessType access Type (0:NONE,1:BLOBS_ONLY,2:CONTAINER_AND_BLOBS)
   * @return bool
   */
  public function createContainer($containerName, $accessType = 0)
  {

    $createContainerOptions = new CreateContainerOptions();
    if ($accessType === 0) {
      $createContainerOptions->setPublicAccess(PublicAccessType::NONE);
    } else if ($accessType === 1) {
      $createContainerOptions->setPublicAccess(PublicAccessType::BLOBS_ONLY);
    } else if ($accessType === 2) {
      $createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);
    }

    try {
      // Create container.
      $this->blobService->createContainer($containerName, $createContainerOptions);
    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
      return false;
    }

    return true;
  }

  /**
   * Delete Container
   */

  /**
   * Get Blobs in Container
   *
   * @param string $containerName container name
   * @param int $start start
   * @param int $end end
   * @return array
   */
  public function getBlobsInContainer($containerName, $start = 0, $end = 0)
  {
    $list = array();

    try {
      $blobList = $this->blobService->listBlobs($containerName);
      $blobs = $blobList->getBlobs();
      $loopCount = 0;
      $this->blobCount = count($blobs);
      foreach ($blobs as $blob) {
        if ($loopCount >= $start && $loopCount < $end) {
          $list[] = $blob;
        }
        $loopCount++;
      }
      $this->setBlobCount = count($blobs);
    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
    }

    return $list;
  }

  /**
   * Upload Blob
   *
   * @param string $containerName container name
   * @param string $blobName blob name
   * @param string $blobPath blob path in local disk
   * @return bool
   */
  public function uploadBlob($containerName, $blobName, $blobPath)
  {
    $options = new CommitBlobBlocksOptions();

    $options->setBlobContentType(mime_content_type($blobPath));

    $contentMd5 = base64_encode(md5_file($blobPath, true));

    $options->setBlobContentMD5($contentMd5);

    try {
      $content = fopen($blobPath, "rb");
      $counter = 1;
      $blockIds = array();

      while (!feof($content))
      {
        $blockId = str_pad($counter, 6, "0", STR_PAD_LEFT);
        $block = new Block();
        $block->setBlockId(base64_encode($blockId));
        $block->setType(BlobBlockType::UNCOMMITTED_TYPE);
        array_push($blockIds, $block);
        $data = fread($content, 1024 * 1024);
        $this->blobService->createBlobBlock($containerName, $blobName, base64_encode($blockId), $data);
        $counter++;
      }

      fclose($content);
      $this->blobService->commitBlobBlocks($containerName, $blobName, $blockIds, $options);

    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
      return false;
    }

    return true;
  }

  /**
   * Download Blob
   * 
   * @param string $containerName container name
   * @param string $blobName blob name
   * @return mix
   */
  public function getBlob($containerName, $blobName)
  {
    try {
      $blob = $this->blobService->getBlob($containerName, $blobName);
      fpassthru($blob->getContentStream());

    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
      return false;
    }
  }

  /**
   * Generate Shared Access URL
   *
   * @param string $containerName container name
   * @param string $blobName blob name
   * @param string $start start iso date
   * @param string $end end iso date
   * @param string $signedIdentifier signed identifier
   * @return string URL
   */
  public function generateSharedAccessUrl($containerName, $blobName, $start, $end, $signedIdentifier = "")
  {
    $stringToSign = "r" . "\n" .
                    $start . "\n" .
                    $end . "\n" .
                    "/" . $this->accountName . "/" . $containerName . "/" . $blobName . "\n" .
                    $signedIdentifier;
    $signature = urlencode(base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accessKey), true)));
    return "http://" . $this->accountName . ".blob.core.windows.net/" . $containerName ."/" . $blobName .
           "?st=" . urlencode($start) . "&se=" . urlencode($end) .
           "&sr=b&sp=r&sig=" . $signature;
  }

  /**
   * Delete Blob
   *
   * @param string $containerName container name
   * @param string $blobName blob name
   * @return bool
   */
  public function deleteBlob($containerName, $blobName)
  {
    try {
      $blob = $this->blobService->deleteBlob($containerName, $blobName);
      return true;

    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
      return false;
    }
  }

  /**
   * Copy Blob
   *
   * @param string $toContainerName container name
   * @param string $toBlobName blob name
   * @param string $fromContainerName container name
   * @param string $fromBlobName blob name
   * @return bool
   */
  public function copyBlob($toContainerName, $toBlobName, $fromContainerName, $fromBlobName)
  {
    try {
      $blob = $this->blobService->copyBlob($toContainerName, $toBlobName, $fromContainerName, $fromBlobName);
      return true;

    } catch(ServiceException $e){
      $this->errorCode = $e->getCode();
      $this->errorMessage = $e->getMessage();
      return false;
    }
  }

  /**
   * get Blob Count
   *
   * @return int
   */
  public function getBlobCount()
  {
    return $this->blobCount;
  }
}

class Pager
{
  private $currentPage;
  private $total;
  private $perPage;

  function __construct($currentPage, $total, $perPage)
  {
    $this->currentPage = intval($currentPage);
    $this->total = intval($total);
    $this->perPage = intval($perPage);
  }

  function pageCount()
  {
    return round($this->total/$this->perPage);
  }

  function getNextPages()
  {
    $pages = array();
    if (intval($this->currentPage) !== round($this->total/$this->perPage)) {
      for ($i=intval($this->currentPage)+1; $i<=round($this->total/$this->perPage); $i++) {
        $pages[] = $i;
      }
    }
    return $pages;
  }

  function getBeforePages()
  {
    $pages = array();
    for ($i=1; $i<$this->currentPage; $i++) {
      $pages[] = $i;
    }
    return $pages;
  }
}

/* switch page and set Azure account and key */
$page = $_POST['page']; /* containerList, blobList, createContainer, uploadBlob, deleteBlob */
$account = $_POST['account'];
$azureKey = $_POST['key'];
if ($account == "" || $azureKey == "") {
  $page = "";
}

/* download */
if ($page === "downloadBlob") {
  $azureBlob = new AzureBlob($account, $azureKey);
  $containerName = $_POST['containerName'];
  $blobName = $_POST['blobName'];
  header("Content-Type: application/octet-stream");
  header('Content-Disposition: attachment; filename="' . $blobName .'"');
  $azureBlob->getBlob($containerName, $blobName);

/* blowse */
} else {
?>
<!doctype html>
<html>
<head>
<meta charset="utf8">
<title>windows Azure storage Blob Editor (ABE)</title>
<style>
body {
  font-size: 1em;
  font: Helvetica, Osaka;
  color: #FFF;
  background-color: #333;
}

a {
  color: #CCC;
  text-decoration: none;
}

a:hover {
  color: #EEE;
  text-decoration: underline;
}

.metadata {
  width: 600px;
  display: none;
  position: absolute;
  border: solid 1px #FFF;
  background-color: #999;
  padding: 3px;
  color: #FFF;
  top: 1em;
  left: 1em;
}

.datarow {
  width: 600px;
}

.datarow .datakey {
  width: 200px;
  text-align: left;
}
.datarow .datavalue {
  width: 400px;
  text-align: right;
}

a:hover .metadata {
  display: block;
  background-color: #999;
  padding: 3px;
  color: #FFF;
}

h1 {
  color: #FFF;
}

.create {
  border: solid 1px #CCC;
  width: 550px;
  clear: both;
}

.labelHeader {
  border-bottom: solid 1px #CCC;
}

.list {
  clear: both;
}

.element {
  width: 1180px;
  clear: both;
}

.element .link {
  width: 55%;
  overflow-x: auto;
  margin-right: 5px;
  float: left;
}

.element .date {
  width: 19%;
  overflow-x: auto;
  margin-right: 5px;
  float: left;
}

.element .button {
  width: 23%;
  float: left;
}

.back {
  clear: both;
}

.login {
  border: solid 1px #CCC;
  width: 560px;
  text-align: left;
  display: block;
}
.login .loginLabel {
  display: block;
  width: 100px;
  float: left;
}

.reconnect {
  padding-top: 16px;
  padding-left: 16px;
  clear: both;
}

.footer {
  padding-top: 20px;
  clear: both;
}
.page {
  height: 30px;
  clear: both;
}
.page .pageButton {
  width: 20px;
  height: 20px;
  border: 1px solid #666;
  float: left;
}
</style>
</head>
<body>
<?php
  /* after login */
  if ($page) {
    $azureBlob = new AzureBlob($account, $azureKey);

    /* Container List */
    if ($page === "containerList") {
      $containerList = $azureBlob->getContainerList();
?>
<div class="create">
  <form action="blob.php" method="POST">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="createContainer">
    <label>Container Name </label><input type="text" name="containerName" size="32" maxlength="128"><br>
    <label>Access Type </label><select name="accessType">
      <option value="0">NONE</option>
      <option value="1">BLOBS_ONLY</option>
      <option value="2">CONTAINER_AND_BLOBS</option>
    </select><br>
    <input type="submit" value="create new Container">
  </form>
</div>

<h1>Your Storage Container List</h1>

<div class="list">

  <div class="element">
    <span class="link labelHeader">Container Name</span>
    <span class="date labelHeader">Last Modified</span>
    <span class="button labelHeader">Function</span>
  </div>

<?php
      foreach ($containerList->getContainers() as $container) {
        $properties = $container->getProperties();
        $metadata = $container->getMetadata();
?>
  <div class="element">
    <span class="link"><a href="<?php echo $container->getUrl();?>"><?php echo $container->getName();?></a></span>
    <span class="date"><?php echo $properties->getLastModified()->format('Y-m-d H:i:s');?></span>
    <span class="button">
      <form action="blob.php" method="POST">
        <input type="hidden" name="account" value="<?php echo $account;?>">
        <input type="hidden" name="key" value="<?php echo $azureKey;?>">
        <input type="hidden" name="page" value="blobList">
        <input type="hidden" name="current" value="1">
        <input type="hidden" name="containerName" value="<?php echo $container->getName();?>">
        <input type="submit" value="show blobs -&gt;">
      </form>
    </span>
  </div>
<?php
      }
?>
</div>

<?php

    /* Blob List */
    } else if ($page === "blobList") {
      $containerName = $_POST['containerName'];
      $current = intval($_POST['current']);
?>
<div class="create">
  <form action="blob.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="uploadBlob">
    <input type="hidden" name="containerName" value="<?php echo $containerName;?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo intval($maxUploadFileSize);?>" />

    <label>Upload File: </label><input type="file" name="blob">
    <input type="submit" value="upload blob">
  </form>
</div>

<h1>Blob List in &quot;<?php echo $containerName;?>&quot;</h1>

<?php
      $startId = intval(($current - 1) * $blobPerPage);
      $endId = intval($current * $blobPerPage);
      $blobList = $azureBlob->getBlobsInContainer($containerName, $startId, $endId);
      $blobListCount = $azureBlob->getBlobCount();;
?>

<div>Total Blobs in this container: <?php echo $blobListCount;?></div>
<div class="page">
<?php
      $pager = new Pager($current, $blobListCount, $blobPerPage);
      $beforePages = $pager->getBeforePages();
      $nextPages = $pager->getNextPages();
      if (count($beforePages) > 0) {
        foreach ($beforePages as $beforePage) {
?>
<!--span class="pageButton"-->
  <form action="blob.php" method="POST">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="blobList">
    <input type="hidden" name="current" value="<?php echo $beforePage;?>">
    <input type="hidden" name="containerName" value="<?php echo $containerName;?>">
    <input class="pageButton" type="submit" value="<?php echo $beforePage;?>">
  </form>
<!--/span-->
<?php
        }
      }
?>
<span class="pageButton"><?php echo $current;?></span>
<?php
      if (count($nextPages) > 0) {
        foreach ($nextPages as $nextPage) {
?>
<!--span class="pageButton"-->
  <form action="blob.php" method="POST">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="blobList">
    <input type="hidden" name="current" value="<?php echo $nextPage;?>">
    <input type="hidden" name="containerName" value="<?php echo $containerName;?>">
    <input class="pageButton" type="submit" value="<?php echo $nextPage;?>">
  </form>
<!--/span-->
<?php
        }
      }
?>
</div>

<div class="list">

  <div class="element">
    <span class="link labelHeader">Blob Name</span>
    <span class="date labelHeader">Last Modified</span>
    <span class="button labelHeader">Function</span>
  </div>

<?php
      $id = 0;
      foreach ($blobList as $blob) {
        $properties = $blob->getProperties();
        $metadata = $blob->getMetadata();
        $sas = $azureBlob->generateSharedAccessUrl($containerName, $blob->getName(), Utilities::isoDate(time()-500), Utilities::isoDate(time()+3000));
?>
  <div class="element">
    <span class="link"><a href="<?php echo $blob->getUrl();?>" target="_blank"><?php echo $blob->getName();?></a></span>
    <span class="date"><?php echo $properties->getLastModified()->format('Y-m-d H:i:s');?></span>
    <span class="button">
      <form action="blob.php" method="POST" style="float:left;">
        <input type="hidden" name="account" value="<?php echo $account;?>">
        <input type="hidden" name="key" value="<?php echo $azureKey;?>">
        <input type="hidden" name="page" value="downloadBlob">
        <input type="hidden" name="containerName" value="<?php echo $containerName;?>">
        <input type="hidden" name="blobName" value="<?php echo $blob->getName();?>">
        <input type="submit" value="download">
      </form>
      <form action="blob.php" method="POST" style="float:left;">
        <input type="hidden" name="account" value="<?php echo $account;?>">
        <input type="hidden" name="key" value="<?php echo $azureKey;?>">
        <input type="hidden" name="page" value="deleteBlob">
        <input type="hidden" name="containerName" value="<?php echo $containerName;?>">
        <input type="hidden" name="blobName" value="<?php echo $blob->getName();?>">
        <input type="submit" value="delete">
      </form>
      <a href="<?php echo $sas;?>" target="_blank">SAS</a>&nbsp;
      <a href="#" onMouseOver="document.getElementById('metadata<?php echo $id;?>').style.display='block';" onMouseOut="document.getElementById('metadata<?php echo $id;?>').style.display='none';">
        metadata
      </a>
      <span class="metadata" id="metadata<?php echo $id?>">
        <div class="datarow"><span class="datakey">FileName&nbsp;</span><span class="datavalue"><?php echo $blob->getName();?></span></div>
<?php
        while (list($key, $value) = each($properties)) {
          list($key1, $key2) = explode("_", $key);
          if (!$value instanceof DateTime) {
?>
        <div class="datarow"><span class="datakey"><?php echo $key2;?>&nbsp;</span><span class="datavalue"><?php echo $value;?></span></div>
<?php
          } else {
?>
        <div class="datarow"><span class="datakey"><?php echo $key2;?>&nbsp;</span><span class="datavalue"><?php echo $value->format('Y-m-d H:i:s');?></span></div>
<?php
          }
        }
?>
      </span></a>
    </span>
  </div>
<?php
        $id++;
      }
?>
</div>

<div class="back">
  <form action="blob.php" method="POST">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="containerList">
    <input type="submit" value="&lt;- show container list">
  </form>
</div>
<?php

    /* Crate Container */
    } else if ($page === "createContainer") {
      $containerName = $_POST['containerName'];
      $accessType = intval($_POST['accessType']);
      if ($azureBlob->createContainer($containerName, $accessType)) {
?>
<h1>Create Success!!</h1>

<div class="back">
  <form action="blob.php" method="POST">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="containerList">
    <input type="submit" value="&lt;- show container list">
  </form>
</div>

<?php
      } else {
        echo 'error <a onClick="history.back();">back</a>';
      }

    /* Upload Blob */
    /* NOT page Blob */
    } else if ($page === "uploadBlob") {
      $containerName = $_POST['containerName'];
      $filename = $_FILES["blob"]["name"];
      if (is_uploaded_file($_FILES["blob"]["tmp_name"]) && $azureBlob->uploadBlob($containerName, $filename, $_FILES["blob"]["tmp_name"])) {
?>
<h1>Upload Success!!</h1>

<div class="back">
  <form action="blob.php" method="POST">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="blobList">
    <input type="hidden" name="current" value="1">
    <input type="hidden" name="containerName" value="<?php echo $containerName;?>">
    <input type="submit" value="&lt;- show blob list">
  </form>
</div>

<?php
      } else {
        echo '<pre>' . $azureBlob->errorMessage . '</pre>';
        echo 'error <a onClick="history.back();">back</a>';
      }

    /* delete Blob */
    } else if ($page === "deleteBlob") {
      $containerName = $_POST['containerName'];
      $filename = $_POST["blobName"];
      if ($azureBlob->deleteBlob($containerName, $filename)) {
?>
<h1>Delete Success!!</h1>

<div class="back">
  <form action="blob.php" method="POST">
    <input type="hidden" name="account" value="<?php echo $account;?>">
    <input type="hidden" name="key" value="<?php echo $azureKey;?>">
    <input type="hidden" name="page" value="blobList">
    <input type="hidden" name="current" value="1">
    <input type="hidden" name="containerName" value="<?php echo $containerName;?>">
    <input type="submit" value="&lt;- show blob list">
  </form>
</div>

<?php
      } else {
        echo '<pre>' . $azureBlob->errorMessage . '</pre>';
        echo 'error <a onClick="history.back();">back</a>';
      }
    }

  /* default */
?>

<div class="reconnect"><a href="./blob.php">&lt;- reconnect</a></div>

<?php
  } else {
?>

<h1>Connect Your Windows Azure Blob Storage</h1>

<div class="login">
  <form action="blob.php" method="POST">
    <input type="hidden" name="page" value="containerList">
    <label class="loginLabel">account</label><input type="text" size="64" maxlength="128" name="account"><br>
    <label class="loginLabel">access key</label><input type="text" size="64" maxlength="128" name="key"><br>
    <input class="submit" type="submit" value="connect">
  </form>
</div>

<?php
  }
?>

<div class="footer">
2013 &copy; pnop, inc.
</div>

</body>
</html>
<?php
}

