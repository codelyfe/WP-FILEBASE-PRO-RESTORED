<?php
class WPFB_FTPSync extends WPFB_RemoteSync {	
	
	var $ftpUser;
	var $ftpHost;
	var $ftpPort = 21;
	var $ftpPass;
	var $ftpSSL = true;
	var $ftpPasv = false;
	
	var $ftpConn;
	
	var $httpUrl;
	
	var $uriVer;
	
	var $sysType;
	
	static $uri_ver = 3;

	static function GetServiceName() { return "FTP"; }
	
	function __construct($title)
	{
		$this->uriVer = self::$uri_ver;
		parent::__construct($title);
	}
	
	protected function PrepareEditForm()
	{		
		return true;
	}
	
	function DisplayFormFields()
	{
		?>
		<tr>
			<th scope="row" valign="top"><label for="ftp-host"><?php _e('FTP Host','wp-filebase') ?></label></th>
			<td><input id="ftp-host" name="ftpHost" type="text" value="<?php echo esc_attr($this->ftpHost); ?>" /></td>
		</tr>
		
		<tr>
			<th scope="row" valign="top"><label for="ftp-port"><?php _e('FTP Port','wp-filebase') ?></label></th>
			<td><input id="ftp-port" name="ftpPort" type="text" class="num" value="<?php echo esc_attr($this->ftpPort); ?>" /></td>
		</tr>
		
		<tr>
			<th scope="row" valign="top"><label for="ftp-user"><?php _e('FTP User','wp-filebase') ?></label></th>
			<td><input id="ftp-user" name="ftpUser" type="text" value="<?php echo esc_attr($this->ftpUser); ?>" /></td>
		</tr>
		
		<tr>
			<th scope="row" valign="top"><label for="ftp-pass"><?php _e('FTP Password','wp-filebase') ?></label></th>
			<td><input id="ftp-pass" name="ftpPass" type="password" value="<?php echo esc_attr($this->ftpPass); ?>" /></td>
		</tr>
		
		<tr>
			<th scope="row" valign="top"></th>
			<td>
				<input id="ftp-ssl" name="ftpSSL" type="checkbox" value="1" <?php checked($this->ftpSSL); ?> />
				<label for="ftp-ssl"><?php _e('Require secure SSL FTP','wp-filebase') ?></label>
			</td>
		</tr>
		
		<tr>
			<th scope="row" valign="top"></th>
			<td>
				<input id="ftp-pasv" name="ftpPasv" type="checkbox" value="1" <?php checked($this->ftpPasv); ?> />
				<label for="ftp-pasv"><?php _e('FTP Passive Mode','wp-filebase') ?></label>
			</td>
		</tr>
		
		<tr>
			<th scope="row" valign="top"><label for="ftp-url"><?php _e('HTTP Url (optional)','wp-filebase') ?></label></th>
			<td><input id="ftp-url" name="httpUrl" type="text" value="<?php echo esc_attr($this->httpUrl); ?>" size="50"/><br />
			<?php printf(__('Enter the HTTP URL of the FTP root directory on the remote server. Example: %s. Download links are mapped using this URL base. Leave empty to use FTP URIs (ftp://...).'),'<code>http://my-external-ftp-site.com/subdir</code>') ?>
			</td>
		</tr>
		
	
		<?php
	}
	
	function Edited($data, $invalidate_uris=false)
	{
		$prev_user = $this->ftpUser;
		$this->ftpHost = $data['ftpHost'];
		$this->ftpPort = absint($data['ftpPort']);
		$this->ftpUser = $data['ftpUser'];
		$this->ftpPass = $data['ftpPass'];
		$prev_ssl = $this->ftpSSL;
		$this->ftpSSL = !empty($data['ftpSSL']);
		$this->ftpPasv = !empty($data['ftpPasv']);
		$prev_httpUrl = $this->httpUrl;
		$this->httpUrl = $data['httpUrl'];
		$res = parent::Edited($data,
				  $invalidate_uris
				  || $prev_httpUrl != $this->httpUrl
				  || $prev_ssl != $this->ftpSSL
				  || empty($this->uriVer)
				  || $this->uriVer != self::$uri_ver);
		if(!$this->GetRemotePath() && empty($prev_user))
			$res['reload_form'] = true;
		
		$this->uriVer = self::$uri_ver;
		
		return $res;
	}
	
	function IsReady() {
		return parent::IsReady();
	}
	
	function GetAccountName()
	{
		return "$this->ftpUser @ $this->ftpHost" . ($this->ftpSSL ? " (FTPS)" : "");
	}
	
	function OpenConnection($for_sync = true)
	{
		wpfb_loadclass('FileUtils');
		
		$this->uris_invalidated = ($this->uris_invalidated || empty($this->uriVer) || $this->uriVer != self::$uri_ver);
		
		if( ((int)$this->ftpPort) <= 0 ) $this->ftpPort = 21;

		$haveSSL = function_exists('ftp_ssl_connect');
		if($this->ftpSSL && !$haveSSL) {
            throw new RemoteSyncException("Required a secure FTP connection, but ftp_ssl_connect does not exists!");
        }

        $this->ftpConn = $haveSSL ? ftp_ssl_connect($this->ftpHost, $this->ftpPort, 30) : false;

		if($this->ftpConn === false ) {
            if($this->ftpSSL)
                throw new RemoteSyncException("Secure FTPS connection to {$this->ftpHost}:{$this->ftpPort} failed! You might want to try an insecure connection if the server does not support FTP SSL.");

            $this->log("Warning: Insecure FTP connection to {$this->ftpHost}:{$this->ftpPort}!");

            if(!function_exists('ftp_connect')) {
                throw new RemoteSyncException("ftp_connect does not exists!");
            }
            $this->ftpConn = ftp_connect($this->ftpHost, $this->ftpPort, 30);
		}
		
		if($this->ftpConn === false) {
			$errno = $errstr = '';
			$connection_test = fsockopen($this->ftpHost, $this->ftpPort, $errno, $errstr, 30);
			if(!$connection_test) {
				throw new RemoteSyncException("FTP connection to {$this->ftpHost}:{$this->ftpPort} failed: $errno $errstr");
			}
			throw new RemoteSyncException("FTP connection to {$this->ftpHost}:{$this->ftpPort} failed!");
		}

		
		if(!empty($this->ftpUser) && !@ftp_login($this->ftpConn, $this->ftpUser, $this->ftpPass))
			throw new RemoteSyncException("FTP login failed!");
		
		ftp_pasv($this->ftpConn, $this->ftpPasv);
		
		$this->sysType = ftp_systype ($this->ftpConn);
				
		return true;
	}
	
	function CloseConnection()
	{
		ftp_close($this->ftpConn);
		$this->ftpConn = null;
	}
	
	function GetFileList($root_path, $names_only=false)
	{
		static $space_escape = true;

		$root_path = trailingslashit($root_path);
		$files = array();

		// by default try to escape spaces, if this fails, try again without
		if(strpos($root_path,' ') !== false && $space_escape) {
			$raws = ftp_rawlist($this->ftpConn, str_replace(' ','\\ ',$root_path));
			!is_array($raws) && ($raws = ftp_rawlist($this->ftpConn, $root_path)) && is_array($raws) && ($space_escape = false);
		} else {
			$raws = ftp_rawlist($this->ftpConn, $root_path);
		}

		if(!is_array($raws))
			throw new RemoteSyncException("FTP: Could not list files in directory '$root_path' ($raws). Try again with passive mode ".($this->ftpPasv ? "disabled" : "enabled"));

		foreach($raws as $r)
		{
			$r = trim($r);
			//drwxr-x---    2 0        8            4096 Jun 14  2009 atd
			$raw = array();
			if(preg_match('/^[a-z-]+\s+[0-9]+\s+\S+\s+\S+\s+([0-9]+)\s+([a-z]+\s+[0-9]+\s+[0-9:]+)\s+(.+)$/i', $r, $raw) && $raw[3] !== '.' && $raw[3] !== '..' && $raw[3] != '.htaccess')
			{
				$f = new WPFB_RemoteFileInfo();
				$f->rev = md5($r);
				$f->size = $raw[1];
				$f->mtime = strtotime($raw[2]);
				$f->path = $root_path.$raw[3];
				$f->is_dir = (strtolower($r{0}) == 'd');
				$files[] = $f;
			}		
		}
		
		return $files;
	}
	
	function DownloadFile($file_info, $local_path, $progress_changed_callback = null)
	{
		$ret = ftp_nb_get($this->ftpConn, $local_path, $file_info->path, FTP_BINARY);
		$t = 0;
		while ($ret == FTP_MOREDATA) {
			if( (time() - $t) >= 1) {
				$t = time();
				if(!empty($progress_changed_callback)) {
			  		call_user_func($progress_changed_callback, @WPFB_FileUtils::GetFileSize($local_path), $file_info->size);
			  	}
			}
			$ret = ftp_nb_continue($this->ftpConn);
		}
		
		if($ret != FTP_FINISHED)
			throw new RemoteSyncException();
	}
	
	function mkdir($remote_path)
	{
		$full_path = "";
		foreach (array_filter(explode("/", $remote_path)) as $part) {
			$full_path .= "/".$part;
			if (!@ftp_chdir($this->ftpConn, $full_path) && !@ftp_mkdir($this->ftpConn, $full_path))
				throw new RemoteSyncException("FTP: Cannot create directory ".$full_path);
		}
		return @ftp_chdir($this->ftpConn, "/");
	}
	
	function UploadFile($local_path, $remote_path, $progress_changed_callback = null) {
		$remote_dir = self::dirname($remote_path);
		$this->mkdir($remote_dir);
		$ret = ftp_nb_put($this->ftpConn, $remote_path, $local_path, FTP_BINARY);
		$size = @WPFB_FileUtils::GetFileSize($local_path);
		if(!empty($progress_changed_callback)) call_user_func($progress_changed_callback, 0, $size);
		while ($ret == FTP_MOREDATA) {
			$ret = ftp_nb_continue($this->ftpConn);
		}
		if(!empty($progress_changed_callback)) call_user_func($progress_changed_callback, $size, $size);
		
		if(is_null($fi = $this->GetRemoteFileInfo($remote_path)))
			throw new RemoteSyncException("FTP: Could not get details of uploaded file!");
		
		return $fi;
	}
	
	protected function CanUpload() {
		return true;
	}
	
	private static function urlencodeFtpPath($path)
	{
		return implode('/', array_map('rawurlencode', explode('/',str_replace('//','/',str_replace('\\','/',$path)))));
		
	}
	
	function GetFileUri($path, &$expires=null)
	{
		$expires = time() + 3600 * 24 * 356 * 2; // 2 years
		$host = $this->ftpHost;
		if($this->ftpPort != 21) $host .= ":".$this->ftpPort;
		if(empty($this->httpUrl))
			return untrailingslashit("ftp://".$host. self::urlencodeFtpPath($path));
		else {
			$plen = strlen($this->GetRemotePath());
			
			if(strpos($this->httpUrl, '://') === false)
				$this->httpUrl = "http://".$this->httpUrl;
			
			return substr($this->httpUrl, 0, 9).untrailingslashit(str_replace('//','/',substr($this->httpUrl, 9).'/'.self::urlencodeFtpPath(substr($path, $plen))));
		}
	}

    protected function GetMaxConcurrentConnections() {
        return 4;
    }
}