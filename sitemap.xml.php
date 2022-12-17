<?php

require_once('wpfb-load.php');

wpfb_loadclass('Core','File');
$files = WPFB_File::GetFiles2(null, new WP_User(0));

header('Content-Type: text/xml');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "\n"
?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach($files as $file) { ?>
<url>
	<loc><?php echo $file->GetUrl(); ?></loc>
	<lastmod><?php echo date('c',$file->GetModifiedTime()); ?></lastmod>
	<changefreq>monthly</changefreq>
	<priority>0.6</priority>
</url>
<?php } ?>
</urlset>
