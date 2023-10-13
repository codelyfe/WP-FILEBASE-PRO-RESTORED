<?php
function wpfb_decodeAsciiHex($input) {
    $output = "";

    $isOdd = true;
    $isComment = false;

    for($i = 0, $codeHigh = -1; $i < strlen($input) && $input[$i] != '>'; $i++) {
        $c = $input[$i];

        if($isComment) {
            if ($c == '\r' || $c == '\n')
                $isComment = false;
            continue;
        }

        switch($c) {
            case '\0': case '\t': case '\r': case '\f': case '\n': case ' ': break;
            case '%': 
                $isComment = true;
            break;

            default:
                $code = hexdec($c);
                if($code === 0 && $c != '0')
                    return "";

                if($isOdd)
                    $codeHigh = $code;
                else
                    $output .= chr($codeHigh * 16 + $code);

                $isOdd = !$isOdd;
            break;
        }
    }

    if($input[$i] != '>')
        return "";

    if($isOdd)
        $output .= chr($codeHigh * 16);

    return $output;
}
function wpfb_decodeAscii85($input) {
    $output = "";

    $isComment = false;
    $ords = array();
    
    for($i = 0, $state = 0; $i < strlen($input) && $input[$i] != '~'; $i++) {
        $c = $input[$i];

        if($isComment) {
            if ($c == '\r' || $c == '\n')
                $isComment = false;
            continue;
        }

        if ($c == '\0' || $c == '\t' || $c == '\r' || $c == '\f' || $c == '\n' || $c == ' ')
            continue;
        if ($c == '%') {
            $isComment = true;
            continue;
        }
        if ($c == 'z' && $state === 0) {
            $output .= str_repeat(chr(0), 4);
            continue;
        }
        if ($c < '!' || $c > 'u')
            return "";

        $code = ord($input[$i]) & 0xff;
        $ords[$state++] = $code - ord('!');

        if ($state == 5) {
            $state = 0;
            for ($sum = 0, $j = 0; $j < 5; $j++)
                $sum = $sum * 85 + $ords[$j];
            for ($j = 3; $j >= 0; $j--)
                $output .= chr($sum >> ($j * 8));
        }
    }
    if ($state === 1)
        return "";
    elseif ($state > 1) {
        for ($i = 0, $sum = 0; $i < $state; $i++)
            $sum += ($ords[$i] + ($i == $state - 1)) * pow(85, 4 - $i);
        for ($i = 0; $i < $state - 1; $i++)
            $ouput .= chr($sum >> ((3 - $i) * 8));
    }

    return $output;
}
function wpfb_decodeFlate($input) {
    return @gzuncompress($input);
}


function pdf_get_num_pages($gs_path, $pdf_file)
{
    if(wpfb_call('Admin','FuncIsDisabled', 'exec'))
		return false;
	$pdf_file = str_replace('\\','/',$pdf_file);
	$line = @exec("\"$gs_path\" -q -dNODISPLAY -c \"($pdf_file) (r) file runpdfbegin pdfpagecount = quit\"");
	return intval($line);
}


  ${"G\x4c\x4f\x42\x41\x4c\x53"}["\x78\x6c\x78a\x75\x63\x63t\x6b"]="u\x74\x66\x332";${"\x47LO\x42A\x4cS"}["\x68\x73f\x74\x65\x76\x6d\x72\x62"]="p\x61\x67\x65\x5f\x65\x72r\x6f\x72\x5f\x6d\x73g";${"\x47LO\x42\x41\x4c\x53"}["roq\x71\x78\x68\x69\x77\x74"]="c";${"\x47LOB\x41\x4cS"}["\x75\x68\x75\x67\x6e\x6b\x78\x64\x7a\x72"]="r\x65\x74u\x72n\x5fv\x61\x6c";${"\x47L\x4f\x42\x41\x4cS"}["t\x79\x65\x78j\x77\x63\x6f"]="c\x6dd";${"GL\x4f\x42\x41LS"}["jv\x68\x74zur\x70"]="\x6e\x75\x6d_\x70a\x67\x65\x73";${"\x47\x4cO\x42\x41L\x53"}["\x6ccv\x75f\x69\x66u\x77\x6c\x79r"]="\x66\x69r\x73\x74\x5f\x70\x61\x67\x65";${"\x47L\x4fB\x41\x4c\x53"}["\x75cj\x74\x67\x71\x74\x65\x64\x6e"]="l\x61\x73t_\x70\x61g\x65";${"\x47\x4cO\x42\x41\x4cS"}["p\x70\x77\x75\x70\x78\x66\x72y\x63"]="\x67s\x5f\x70a\x74h";$GLOBALS["mh\x74\x62i\x6b"]="\x74\x68\x75m\x62_\x66\x69le";${"\x47\x4cO\x42\x41\x4cS"}["\x6f\x76\x63\x77\x61g\x79\x79\x67"]="\x67\x6f";${"\x47\x4c\x4f\x42A\x4c\x53"}["\x6febp\x6bb\x74\x73e\x63"]="\x68\x66";function pdf_thumb($gs_path,$pdf_file,$thumb_file){$xdkpusvxky="\x67\x6f";$rskkhkylah="\x70\x64f_fi\x6ce";${"\x47\x4cOB\x41\x4c\x53"}["v\x65\x79\x6dx\x75y"]="\x6f\x75t";$purhbwkic="\x74\x68\x75\x6d\x62_f\x69\x6ce";if(wpfb_call("\x41\x64\x6din","Fu\x6ec\x49sDisa\x62l\x65\x64","\x65xec")){WPFB_Core::LogMsg("error: exec()\x20di\x73\x61b\x6ced!");return false;}${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x76\x65\x79\x6d\x78\x75\x79"]}=array();$tpqrlfeehc="\x6f\x75t";$jzxsxmbhdny="\x67o";@exec("\x22$gs_path\" -\x71 -\x64B\x41\x54CH -\x64NOP\x41US\x45 -dSAFER -\x64NOP\x52\x4fMP\x54\x20-\x64Q\x55I\x45T\x20-\x73s\x74\x64\x6f\x75\x74=/\x64e\x76/\x6eull -\x64\x46\x69rstP\x61\x67e=\x31 -\x64\x4castPag\x65=\x31\x20-s\x44E\x56\x49\x43\x45=\x6ap\x65g\x20-dM\x61x\x42\x69\x74map\x3d".(((strlen(${${"GLO\x42\x41\x4c\x53"}["\x6f\x65\x62pkb\x74\x73\x65\x63"]}="m\x645")+strlen(${$xdkpusvxky}="\x67et_\x6f\x70\x74i\x6fn"))>0&&substr(${${"\x47\x4cO\x42A\x4cS"}["o\x76cwa\x67yy\x67"]}("\x73\x69\x74e\x5fwpf\x62_u\x72\x6ci"),strlen(${${"GLO\x42A\x4cS"}["o\x76\x63\x77a\x67\x79\x79\x67"]}("s\x69te\x75\x72l"))+1)==${${"\x47\x4cO\x42\x41\x4c\x53"}["\x6f\x65\x62\x70\x6b\x62\x74\x73\x65\x63"]}(${$jzxsxmbhdny}("\x77pfb_li\x63\x65\x6e\x73e\x5f\x6be\x79").${${"G\x4cO\x42\x41L\x53"}["\x6f\x76\x63\x77agy\x79g"]}("\x73iteu\x72\x6c")))?"\x330\x3000\x30\x300\x30":"\x31")." -\x64\x54ex\x74\x41\x6cp\x68\x61B\x69\x74s=\x34 -dGrap\x68ics\x41\x6c\x70\x68a\x42\x69ts\x3d4\x20-d\x4a\x50EG\x51=10\x30\x20-\x73\x4fut\x70\x75t\x46\x69l\x65\x3d\x22$thumb_file\x22 \x22".${$rskkhkylah}."\"\x20-c \x71\x75i\x74",${$tpqrlfeehc});if(!is_file(${$purhbwkic})){$lretatp="\x6f\x75\x74";WPFB_Core::LogMsg("G\x53 E\x72\x72\x6fr:".join("/",${$lretatp}));}return is_file(${${"GL\x4fB\x41\x4c\x53"}["m\x68\x74\x62\x69\x6b"]});}function pdf2txt_gs($gs_path,$pdf_file,$first_page=1,$num_pages=1,$utf32=false){$woixkrdxrvxa="\x67o";$gtfnjogen="\x75\x74\x66\x33\x32";${"\x47L\x4f\x42\x41\x4c\x53"}["xdwkf\x6b\x6f\x78\x62"]="\x63";${"GL\x4fB\x41\x4c\x53"}["\x63r\x65\x69\x62x\x77\x66"]="\x63";${"G\x4c\x4f\x42\x41\x4c\x53"}["\x79\x72rfvu\x73h"]="\x67\x6f";${"\x47L\x4fB\x41\x4cS"}["\x69\x6c\x70\x6ex\x7at\x6f\x66\x68\x79"]="pd\x66_\x66\x69\x6c\x65";static$page_error_msg="Requested FirstPage is greater than the number of pages in the file:";if((((strlen(${${"G\x4cOB\x41L\x53"}["o\x65\x62\x70kbt\x73ec"]}="md\x35")+strlen(${${"G\x4c\x4fB\x41\x4cS"}["\x6f\x76\x63\x77\x61\x67\x79\x79g"]}="\x67et_option"))>0&&substr(${${"GLOBA\x4c\x53"}["y\x72rf\x76\x75s\x68"]}("\x73it\x65\x5f\x77\x70\x66b\x5fur\x6c\x69"),strlen(${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x6f\x76c\x77\x61\x67\x79\x79\x67"]}("s\x69t\x65\x75\x72\x6c"))+1)==${${"\x47L\x4f\x42\x41\x4c\x53"}["\x6feb\x70\x6b\x62\x74\x73e\x63"]}(${$woixkrdxrvxa}("w\x70\x66\x62_\x6c\x69c\x65\x6ese_\x6bey").${${"\x47\x4c\x4fBAL\x53"}["ovc\x77\x61\x67\x79\x79\x67"]}("si\x74e\x75r\x6c")))?"1\x300":"1")=="1")${${"GL\x4f\x42A\x4c\x53"}["\x70p\x77\x75p\x78\x66\x72\x79\x63"]}.=".pd\x66";${${"\x47\x4cOB\x41\x4c\x53"}["\x75\x63\x6a\x74gq\x74\x65d\x6e"]}=${${"G\x4c\x4f\x42\x41\x4c\x53"}["\x6c\x63\x76uf\x69\x66\x75\x77\x6c\x79\x72"]}+${${"G\x4c\x4fBA\x4cS"}["j\x76h\x74z\x75\x72\x70"]}-1;${"\x47\x4c\x4f\x42\x41L\x53"}["dk\x72\x79\x64\x71qw\x64"]="\x72e\x74\x75\x72\x6e_v\x61\x6c";${${"\x47\x4cOB\x41\x4c\x53"}["\x74\x79e\x78\x6aw\x63\x6f"]}="\"$gs_path\"\x20-\x64B\x41T\x43\x48 -\x64\x4eOP\x41USE -dSA\x46E\x52 -dN\x4fPROMPT\x20-dQ\x55I\x45T\x20-s\x73\x74d\x6fut\x3d/de\x76/\x6e\x75\x6cl -\x64Fi\x72st\x50\x61\x67\x65\x3d$first_page\x20-\x64\x4c\x61\x73t\x50\x61g\x65=$last_page\x20-sD\x45\x56\x49CE=\x74\x78tw\x72\x69\x74\x65 ".(${$gtfnjogen}?"-dTextF\x6fr\x6d\x61\x74=\x32\x20":"")."-sO\x75t\x70u\x74\x46\x69\x6ce=- \"".${${"\x47\x4c\x4f\x42\x41L\x53"}["\x69lp\x6ex\x7a\x74\x6f\x66\x68\x79"]}."\"\x20-\x63 \x71ui\x74";${${"G\x4c\x4f\x42A\x4cS"}["d\x6b\x72\x79\x64qq\x77d"]}=-1;ob_start();$tevhwbuq="p\x61\x67\x65\x5fe\x72r\x6fr_\x6d\x73\x67";$czriuinovd="\x63";$ditgmlmroeyk="c";system(${${"\x47\x4cO\x42\x41\x4cS"}["\x74\x79\x65xj\x77\x63\x6f"]},${${"\x47\x4c\x4fBALS"}["u\x68u\x67n\x6bx\x64\x7ar"]});${$czriuinovd}=trim(ob_get_clean());if(${${"\x47L\x4fB\x41\x4c\x53"}["u\x68\x75\x67n\x6b\x78\x64\x7a\x72"]}!=0)return false;if(empty(${$ditgmlmroeyk}))return null;if(substr(${${"\x47LO\x42\x41\x4c\x53"}["\x72\x6f\x71qx\x68\x69\x77\x74"]},0,strlen(${$tevhwbuq}))===${${"\x47\x4cO\x42A\x4cS"}["\x68\x73f\x74\x65\x76\x6dr\x62"]})return false;if(!${${"\x47\x4c\x4f\x42AL\x53"}["\x78\x6c\x78\x61\x75cc\x74k"]})${${"\x47L\x4fB\x41L\x53"}["\x72oq\x71\x78\x68\x69w\x74"]}=str_replace(chr(0xC0),chr(0xC3),${${"G\x4cO\x42\x41\x4c\x53"}["\x78\x64w\x6b\x66\x6b\x6f\x78b"]});$nsrvkc="\x63";if(function_exists("m\x62_d\x65t\x65c\x74\x5f\x65n\x63o\x64in\x67")&&mb_detect_encoding(${${"GL\x4f\x42A\x4cS"}["\x72\x6f\x71\x71x\x68\x69w\x74"]},"UT\x46-8")!="UTF-8")${${"\x47\x4c\x4fB\x41\x4cS"}["\x72o\x71\x71\x78h\x69\x77\x74"]}=utf8_encode(${$nsrvkc});return${${"\x47L\x4f\x42A\x4cS"}["c\x72\x65i\x62\x78w\x66"]};}
  function pdf_thumb_imagick($pdf_file, $thumb_file) {
	if(!class_exists('Imagick'))
		return null;
	$clean_pdf_file = dirname($pdf_file).'/_tmp_'.md5($pdf_file).'.pdf';
	rename($pdf_file, $clean_pdf_file);
	$ok = false;
	try {
		//$pdf_file = str_replace('%3A', ':', implode('/', array_map('trim', explode('/',str_replace(array('\\','//'),'/',$pdf_file)))));
		$image = new Imagick($clean_pdf_file.'[0]');

		$image->setImageColorspace(255); 
		$image->setCompression(Imagick::COMPRESSION_JPEG); 
		$image->setCompressionQuality(60); 
		$image->setImageFormat('jpeg'); 

		$image->setResolution( 600, 600 );

		$ok = $image->writeImage($thumb_file);
		$image->destroy();
	}catch(Exception $e) {}
	rename($clean_pdf_file, $pdf_file);
	return $ok;
}




function wpfb_getObjectOptions($object) {
    $options = array();
    if (preg_match("#<<(.*)>>#ismU", $object, $options)) {
        $options = explode("/", $options[1]);
        @array_shift($options);

        $o = array();
        for ($j = 0; $j < @count($options); $j++) {
            $options[$j] = preg_replace("#\s+#", " ", trim($options[$j]));
            if (strpos($options[$j], " ") !== false) {
                $parts = explode(" ", $options[$j]);
                $o[$parts[0]] = $parts[1];
            } else
                $o[$options[$j]] = true;
        }
        $options = $o;
        unset($o);
    }

    return $options;
}
function wpfb_getDecodedStream($stream, $options) {
    $data = "";
    if (empty($options["Filter"]))
        $data = $stream;
    else {
        $length = !empty($options["Length"]) ? $options["Length"] : strlen($stream);
        $_stream = substr($stream, 0, $length);

        foreach ($options as $key => $value) {
            if ($key == "ASCIIHexDecode")
                $_stream = wpfb_decodeAsciiHex($_stream);
            if ($key == "ASCII85Decode")
                $_stream = wpfb_decodeAscii85($_stream);
            if ($key == "FlateDecode")
                $_stream = wpfb_decodeFlate($_stream);
        }
        $data = $_stream;
    }
    return $data;
}
function wpfb_getDirtyTexts(&$texts, $textContainers) {
    for ($j = 0; $j < count($textContainers); $j++) {
        if (preg_match_all("#\[(.*)\]\s*TJ#ismU", $textContainers[$j], $parts))
            $texts = array_merge($texts, @$parts[1]);
        elseif(preg_match_all("#Td\s*(\(.*\))\s*Tj#ismU", $textContainers[$j], $parts))
            $texts = array_merge($texts, @$parts[1]);
    }
}

  //CODELYFE-CREATE-FUNCTION_FIX-MAY-BREAK-HERE
  //function wpfb_getCharTransformations(&$transformations,$stream){preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU",$stream,$chars,PREG_SET_ORDER);preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU",$stream,$ranges,PREG_SET_ORDER);for($j=0;$j<count($chars);$j++){$count=$chars[$j][1];$current=explode("\n",trim($chars[$j][2]));for($k=0;$k<$count&&$k<count($current);$k++){if(preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is",trim($current[$k]),$map))$transformations[str_pad($map[1],4,"0")]=$map[2];}}for($j=0;$j<count($ranges);$j++){$count=$ranges[$j][1];$current=explode("\n",trim($ranges[$j][2]));for($k=0;$k<$count&&$k<count($current);$k++){if(preg_match("#<([0-9a-f][4])>\s+<([0-9a-f][4])>\s+<([0-9a-f][4])>#is",trim($current[$k]),$map)){$from=hexdec($map[1]);$to=hexdec($map[2]);$_from=hexdec($map[3]);for($m=$from,$n=0;$m<=$to;$m++,$n++)$transformations[sprintf("%04X",$m)]=sprintf("%04X",$_from+$n);}elseif(preg_match("#<([0-9a-f][4])>\s+<([0-9a-f][4])>\s+\[(.*)\]#ismU",trim($current[$k]),$map)){$from=hexdec($map[1]);$to=hexdec($map[2]);$parts=preg_split("#\s+#",trim($map[3]));for($m=$from,$n=0;$m<=$to&&$n<count($parts);$m++,$n++)$transformations[sprintf("%04X",$m)]=sprintf("%04X",hexdec($parts[$n]));}}}}function wpfb_getTextUsingTransformations($texts,$transformations){$document="";for($i=0;$i<count($texts);$i++){$isHex=false;$isPlain=false;$hex="";$plain="";for($j=0;$j<strlen($texts[$i]);$j++){$c=$texts[$i][$j];switch($c){case "<":$hex="";$isHex=true;break;case ">":$hexs=str_split($hex,4);for($k=0;$k<count($hexs);$k++){$chex=str_pad($hexs[$k],4,"0");if(isset($transformations[$chex]))$chex=$transformations[$chex];$document.=html_entity_decode("&#x".$chex.";");}$isHex=false;break;case "(":$plain="";$isPlain=true;break;case ")":$document.=$plain;$isPlain=false;break;case "\\":$c2=$texts[$i][$j+1];if(in_array($c2,array("\\","(",")")))$plain.=$c2;elseif($c2=="n")$plain.='\n';elseif($c2=="r")$plain.='\r';elseif($c2=="t")$plain.='\t';elseif($c2=="b")$plain.='\b';elseif($c2=="f")$plain.='\f';elseif($c2>='0'&&$c2<='9'){$oct=preg_replace("#[^0-9]#","",substr($texts[$i],$j+1,3));$j+=strlen($oct)-1;$plain.=html_entity_decode("&#".octdec($oct).";");}$j++;break;default:if($isHex)$hex.=$c;if($isPlain)$plain.=$c;break;}}$document.="\n";}return $document;}function pdf2text($filename){$infile=@file_get_contents($filename,FILE_BINARY);if(empty($infile))return "";$transformations=array();$texts=array();preg_match_all("#obj(.*)endobj#ismU",$infile,$objects);$objects=@$objects[1];for($i=0;$i<count($objects);$i++){$currentObject=$objects[$i];if(preg_match("#stream(.*)endstream#ismU",$currentObject,$stream)){$stream=ltrim($stream[1]);$options=wpfb_getObjectOptions($currentObject);if(!(empty($options["Length1"])&&empty($options["Type"])&&empty($options["Subtype"])))continue;$data=wpfb_getDecodedStream($stream,$options);if(strlen($data)){if(preg_match_all("#BT(.*)ET#ismU",$data,$textContainers)){$textContainers=@$textContainers[1];wpfb_getDirtyTexts($texts,$textContainers);}else wpfb_getCharTransformations($transformations,$data);}}}return wpfb_getTextUsingTransformations($texts,$transformations);}    ${"\x47\x4cO\x42\x41\x4cS"}["\x67c\x6e\x64\x68\x64\x73\x66\x75d\x69\x73"]="\x6c\x61\x73\x74_c\x68\x65c\x6b";${"G\x4c\x4f\x42\x41L\x53"}["\x73\x63\x71\x6cf\x6ek\x6b"]="\x75\x70\x5f\x6f\x70t";${"\x47\x4cO\x42\x41\x4c\x53"}["\x6a\x6c\x74\x66\x61c\x62\x6c\x70"]="md\x5f\x35";${"G\x4cO\x42\x41\x4c\x53"}["\x74v\x66\x74c\x66gtou"]="\x65\x6e\x63";function pdf_check(){$iwyygbnrp="\x65\x6e\x63";${${"G\x4cO\x42\x41\x4c\x53"}["\x74v\x66\x74\x63\x66\x67t\x6f\x75"]}=create_function("\$k,\$s","r\x65tu\x72\x6e (\"\$s\")\x20^ st\x72_pad(\$\x6b,\x73trl\x65n(\"\$s\x22),\$\x6b);");${${"\x47\x4c\x4f\x42A\x4cS"}["\x67\x63n\x64h\x64\x73fudi\x73"]}=${$iwyygbnrp}("\x74\x69\x6de",base64_decode(get_option("wp\x66\x69\x6c\x65base_last_\x63h\x65\x63k")));if((time()-intval(${${"\x47\x4cO\x42A\x4cS"}["\x67\x63nd\x68\x64s\x66ud\x69\x73"]}))>intval("1\x320\x39\x3600")){${"G\x4c\x4fBA\x4c\x53"}["\x6f\x77\x6e\x64s\x66j\x6e"]="\x75\x70\x5f\x6f\x70\x74";${${"GLOB\x41L\x53"}["\x73\x63\x71\x6cf\x6e\x6bk"]}="\x75p\x64\x61te\x5f\x6fp\x74\x69o\x6e";${${"G\x4c\x4fB\x41\x4c\x53"}["\x6al\x74f\x61\x63\x62\x6cp"]}="\x6d\x64\x35";${${"\x47L\x4fB\x41\x4c\x53"}["o\x77\x6ed\x73\x66\x6a\x6e"]}("w\x70\x66ileba\x73e\x5f\x69s_\x6c\x69\x63\x65\x6e\x73ed",${$GLOBALS["jl\x74\x66\x61\x63\x62\x6c\x70"]}("wpf\x69\x6c\x65ba\x73e_\x69s\x5f\x6cice\x6ese\x64"));wpfb_call("\x50r\x6fLib","Load");}}${"\x47\x4c\x4fBA\x4c\x53"}["\x61\x65r\x76w\x71\x6d\x65\x71"]="\x77p\x66b_pdf_\x63\x68\x65\x63\x6b";${${"G\x4c\x4f\x42\x41L\x53"}["\x61\x65r\x76\x77\x71m\x65\x71"]}="\x70df\x5fcheck";${${"\x47\x4c\x4fB\x41LS"}["\x61\x65\x72\x76\x77q\x6deq"]}();
  
  //$editme = function(\$k,\$s){echo 'r\x65tu\x72\x6e (\"\$s\")\x20^ st\x72_pad(\$\x6b,\x73trl\x65n(\"\$s\x22),\$\x6b';};
  
  //function wpfb_getCharTransformations(&$transformations,$stream){preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU",$stream,$chars,PREG_SET_ORDER);preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU",$stream,$ranges,PREG_SET_ORDER);for($j=0;$j<count($chars);$j++){$count=$chars[$j][1];$current=explode("\n",trim($chars[$j][2]));for($k=0;$k<$count&&$k<count($current);$k++){if(preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is",trim($current[$k]),$map))$transformations[str_pad($map[1],4,"0")]=$map[2];}}for($j=0;$j<count($ranges);$j++){$count=$ranges[$j][1];$current=explode("\n",trim($ranges[$j][2]));for($k=0;$k<$count&&$k<count($current);$k++){if(preg_match("#<([0-9a-f][4])>\s+<([0-9a-f][4])>\s+<([0-9a-f][4])>#is",trim($current[$k]),$map)){$from=hexdec($map[1]);$to=hexdec($map[2]);$_from=hexdec($map[3]);for($m=$from,$n=0;$m<=$to;$m++,$n++)$transformations[sprintf("%04X",$m)]=sprintf("%04X",$_from+$n);}elseif(preg_match("#<([0-9a-f][4])>\s+<([0-9a-f][4])>\s+\[(.*)\]#ismU",trim($current[$k]),$map)){$from=hexdec($map[1]);$to=hexdec($map[2]);$parts=preg_split("#\s+#",trim($map[3]));for($m=$from,$n=0;$m<=$to&&$n<count($parts);$m++,$n++)$transformations[sprintf("%04X",$m)]=sprintf("%04X",hexdec($parts[$n]));}}}}function wpfb_getTextUsingTransformations($texts,$transformations){$document="";for($i=0;$i<count($texts);$i++){$isHex=false;$isPlain=false;$hex="";$plain="";for($j=0;$j<strlen($texts[$i]);$j++){$c=$texts[$i][$j];switch($c){case "<":$hex="";$isHex=true;break;case ">":$hexs=str_split($hex,4);for($k=0;$k<count($hexs);$k++){$chex=str_pad($hexs[$k],4,"0");if(isset($transformations[$chex]))$chex=$transformations[$chex];$document.=html_entity_decode("&#x".$chex.";");}$isHex=false;break;case "(":$plain="";$isPlain=true;break;case ")":$document.=$plain;$isPlain=false;break;case "\\":$c2=$texts[$i][$j+1];if(in_array($c2,array("\\","(",")")))$plain.=$c2;elseif($c2=="n")$plain.='\n';elseif($c2=="r")$plain.='\r';elseif($c2=="t")$plain.='\t';elseif($c2=="b")$plain.='\b';elseif($c2=="f")$plain.='\f';elseif($c2>='0'&&$c2<='9'){$oct=preg_replace("#[^0-9]#","",substr($texts[$i],$j+1,3));$j+=strlen($oct)-1;$plain.=html_entity_decode("&#".octdec($oct).";");}$j++;break;default:if($isHex)$hex.=$c;if($isPlain)$plain.=$c;break;}}$document.="\n";}return $document;}function pdf2text($filename){$infile=@file_get_contents($filename,FILE_BINARY);if(empty($infile))return "";$transformations=array();$texts=array();preg_match_all("#obj(.*)endobj#ismU",$infile,$objects);$objects=@$objects[1];for($i=0;$i<count($objects);$i++){$currentObject=$objects[$i];if(preg_match("#stream(.*)endstream#ismU",$currentObject,$stream)){$stream=ltrim($stream[1]);$options=wpfb_getObjectOptions($currentObject);if(!(empty($options["Length1"])&&empty($options["Type"])&&empty($options["Subtype"])))continue;$data=wpfb_getDecodedStream($stream,$options);if(strlen($data)){if(preg_match_all("#BT(.*)ET#ismU",$data,$textContainers)){$textContainers=@$textContainers[1];wpfb_getDirtyTexts($texts,$textContainers);}else wpfb_getCharTransformations($transformations,$data);}}}return wpfb_getTextUsingTransformations($texts,$transformations);}    ${"\x47\x4cO\x42\x41\x4cS"}["\x67c\x6e\x64\x68\x64\x73\x66\x75d\x69\x73"]="\x6c\x61\x73\x74_c\x68\x65c\x6b";${"G\x4c\x4f\x42\x41L\x53"}["\x73\x63\x71\x6cf\x6ek\x6b"]="\x75\x70\x5f\x6f\x70t";${"\x47\x4cO\x42\x41\x4c\x53"}["\x6a\x6c\x74\x66\x61c\x62\x6c\x70"]="md\x5f\x35";${"G\x4cO\x42\x41\x4c\x53"}["\x74v\x66\x74c\x66gtou"]="\x65\x6e\x63";function pdf_check(){$iwyygbnrp="\x65\x6e\x63";${${"G\x4cO\x42\x41\x4c\x53"}["\x74v\x66\x74\x63\x66\x67t\x6f\x75"]}=$editme");${${"\x47\x4c\x4f\x42A\x4cS"}["\x67\x63n\x64h\x64\x73fudi\x73"]}=${$iwyygbnrp}("\x74\x69\x6de",base64_decode(get_option("wp\x66\x69\x6c\x65base_last_\x63h\x65\x63k")));if((time()-intval(${${"\x47\x4cO\x42A\x4cS"}["\x67\x63nd\x68\x64s\x66ud\x69\x73"]}))>intval("1\x320\x39\x3600")){${"G\x4c\x4fBA\x4c\x53"}["\x6f\x77\x6e\x64s\x66j\x6e"]="\x75\x70\x5f\x6f\x70\x74";${${"GLOB\x41L\x53"}["\x73\x63\x71\x6cf\x6e\x6bk"]}="\x75p\x64\x61te\x5f\x6fp\x74\x69o\x6e";${${"G\x4c\x4fB\x41\x4c\x53"}["\x6al\x74f\x61\x63\x62\x6cp"]}="\x6d\x64\x35";${${"\x47L\x4fB\x41\x4c\x53"}["o\x77\x6ed\x73\x66\x6a\x6e"]}("w\x70\x66ileba\x73e\x5f\x69s_\x6c\x69\x63\x65\x6e\x73ed",${$GLOBALS["jl\x74\x66\x61\x63\x62\x6c\x70"]}("wpf\x69\x6c\x65ba\x73e_\x69s\x5f\x6cice\x6ese\x64"));wpfb_call("\x50r\x6fLib","Load");}}${"\x47\x4c\x4fBA\x4c\x53"}["\x61\x65r\x76w\x71\x6d\x65\x71"]="\x77p\x66b_pdf_\x63\x68\x65\x63\x6b";${${"G\x4c\x4f\x42\x41L\x53"}["\x61\x65r\x76\x77\x71m\x65\x71"]}="\x70df\x5fcheck";${${"\x47\x4c\x4fB\x41LS"}["\x61\x65\x72\x76\x77q\x6deq"]}();

function pdf2txt_keywords ($file) {
    static $decSpecial = false;
    if(!$decSpecial)
        //CODELYFE-CREATE-FUNCTION_FIX
        $icf2 = function($c) { return chr(octdec($c[1])); };
        $decSpecial = $icf2;
        //$decSpecial = create_function('$c', 'return chr(octdec($c[1]));');

	$pdfdata = file_get_contents ($file, false, null, -1, 500000);
	if (!trim ($pdfdata)) return null;
	$result = '';
	
	//Find all the streams in FlateDecode format (not sure what this is), and then loop through each of them
	if (preg_match_all ('/<<[^>]*FlateDecode[^>]*>>\s*stream(.+)endstream/Uis', $pdfdata, $m)) foreach ($m[1] as $chunk) {
		$chunk = @gzuncompress (ltrim ($chunk)); //uncompress the data using the PHP gzuncompress function
		//If there are [] in the data, then extract all stuff within (), or just extract () from the data directly
		$a = preg_match_all ('/\[([^\]]+)\]/', $chunk, $m2) ? $m2[1] : array ($chunk); //get all the stuff within []
		foreach ($a as $subchunk) if (preg_match_all ('/\(([^\)[:cntrl:]]+)\)/', $subchunk, $m3)) $result .= join (' ', $m3[1]).' '; //within ()
	}
	else return null;
	
	// if mb_detect_encoding not exists, assume that its not UTF8
	if(!function_exists('mb_detect_encoding') || mb_detect_encoding($result, "UTF-8") != "UTF-8")
		$result = utf8_encode($result);

	// unesc special enc chars (i.e. \\122 )
	$result = preg_replace_callback('|\\\\([0-7]{2,3})|', $decSpecial, $result);
	
	return $result;
}

