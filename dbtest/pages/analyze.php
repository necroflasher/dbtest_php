<?php

# idea: identify tools used to create
# - ffmpeg (video object)
#   - uncompressed
#   - SWF6
#   - xmin/xmax zero
#   - only string is "!object-name video"
# - flash cs6 (xml says it)
# - haxe
#   - http://r1.local:8080/dbtest.php?do=analyze&md5=8EDDB6A2489EF741E034DF0BA0001A3B
# - circle ones
#   - http://r1.local:8080/dbtest.php?do=analyze&md5=770CEAF926C99A1EFD99609983CF5929
# - pdf conversion

# WANT: download link for local analysis
# TODO: print exit code on failure, also get it from read_db

function get_valid_charset()
{
	$cs = @strval($_GET['charset']);
	$cs = strtoupper($cs);
	switch ($cs)
	{
		case 'CP1250':
		case 'CP1251':
		case 'CP1252':
		case 'CP1253':
		case 'CP1254':
		case 'CP1255':
		case 'CP1256':
		case 'CP1257':
		case 'CP1258':
		case 'CP874':
		case 'CP932':
		case 'CP936':
		case 'CP949':
		case 'CP950':
			return $cs;
		default:
			return 'CP1252';
	}
}

# in: data filename
# out: path to the tar.gz containing that file
function get_data_targz($data_filename)
{
	return f_tars_dir.'/f-'.substr($data_filename, 0, 4).'.tar.gz';
}

# in: data filename
# out: path to the tar.gz index db containing info about that file
function get_data_db($data_filename)
{
	return f_dbs_dir.'/f-'.substr($data_filename, 0, 4).'.db';
}

# in: data filename
# out: the file's full path inside the tar.gz
function get_data_tarpath($data_filename)
{
	$abcd = substr($data_filename, 0, 4);
	$ef   = substr($data_filename, 4, 2);
	return "$abcd/$ef/$data_filename";
}

function get_tar_file_info($tardb, $tarpath)
{
	$row = false;

	$db = new SQLite3($tardb, SQLITE3_OPEN_READWRITE);
	$db->exec('PRAGMA journal_mode=WAL');

	$q = $db->prepare('
	SELECT offset, filesize, LOWER(HEX(md5)) AS datamd5
	FROM files
	WHERE filename=?;
	');
	$q->bindValue(1, $tarpath, SQLITE3_TEXT);

	$r = $q->execute();
	if ($r)
	{
		if ($r->numColumns())
			$row = $r->fetchArray(SQLITE3_ASSOC);

		$r->finalize();
	}

	$q->close();
	$db->close();

	return $row;
}

# detects locale/charset based on font names
class LocaleDetect
{
	var $matches; # charset -> match count
	var $misses;  # font name -> true (unrecognized fonts)
	var $fonts;   # mangled font name -> suggested charset

	# current "display charset" (what the analyzer assumes the font names to be)
	var $display_cs;

	function __construct($display_cs)
	{
		$this->matches = [];
		$this->misses = [];
		$this->fonts = [];
		$this->display_cs = $display_cs;

		# system default fonts

		# http://r1.local:8080/dbtest.php?do=analyze&md5=0E32408FA1A6FCD3D20C5A67A0287FF2
		$this->_add_font('CP932', 'MS UI Gothic');
		# http://r1.local:8080/dbtest.php?do=analyze&md5=728598681C66CCA1E031D8B3E3C2D5BC
		$this->_add_font('CP932', 'ＭＳ ゴシック');
		# http://r1.local:8080/dbtest.php?do=analyze&md5=9D09B876297395DA04AA5333188B3965
		$this->_add_font('CP932', 'ＭＳ Ｐゴシック');
		# http://r1.local:8080/dbtest.php?do=analyze&md5=57AE8B1BD83076977E0CFCBF30BC593F
		$this->_add_font('CP932', 'ＭＳ 明朝');
		$this->_add_font('CP932', 'ＭＳ Ｐ明朝');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=C92FCACD2F0C16C5F3D3EAD78AC778C2
		# hom os x
		$this->_add_font('CP932', 'Osaka');

		# https://en.wikipedia.org/wiki/Arial#Code_page_variants
		# "aliases created in the FontSubstitutes section of WIN.INI"
		$this->_add_font('CP1250', 'Arial CE'); # central european
		$this->_add_font('CP1253', 'Arial Greek');
		$this->_add_font('CP1257', 'Arial Baltic');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=A00DC108DAA5B7EFFA5B85500953FA5B
		# TODO: this is SWF6, find one with SWF1-5
		$this->_add_font('CP1251', 'Arial Cyr');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=BC390CBEC8A4CC8E1E9417EE9D68E037
		$this->_add_font('CP1254', 'Arial Tur');

		# web fonts

		# http://r1.local:8080/dbtest.php?do=analyze&md5=E8C6C47AB5FD57130A3F87693386E90D
		$this->_add_font('CP932', 'YAKITORI');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=9D09B876297395DA04AA5333188B3965
		$this->_add_font('CP932', 'HGP創英角ﾎﾟｯﾌﾟ体');
		$this->_add_font('CP932', 'HGS創英角ﾎﾟｯﾌﾟ体');
		$this->_add_font('CP932', 'HG創英角ﾎﾟｯﾌﾟ体');
		$this->_add_font('CP932', 'HG白洲太楷書体');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=A09937DC197D81DD2DD969BF67FB63AC
		$this->_add_font('CP932', 'ヒラギノ明朝 Pro W6');
		$this->_add_font('CP932', 'ヒラギノ角ゴ Pro W3');
		$this->_add_font('CP932', 'ヒラギノ角ゴ Pro W6');
		$this->_add_font('CP932', 'ヒラギノ角ゴ Std W8');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=268E12949149ABA9A6D0ED48B172F3A3
		$this->_add_font('CP932', 'あくあＰフォント');
		$this->_add_font('CP932', 'ＤＦ勘亭流');
		$this->_add_font('CP932', 'ＤＦＧ勘亭流');
		$this->_add_font('CP932', 'ＤＦＧ唐風隷書体W5');
		$this->_add_font('CP932', 'ＤＦＧ流隷体W7');
		$this->_add_font('CP932', 'ＤＦＰPOP1体');
		$this->_add_font('CP932', 'ＤＦＰPOP2体W9');
		$this->_add_font('CP932', 'ＤＦＰまるもじ体');
		$this->_add_font('CP932', 'ＤＦＰまるもじ体W9');
		$this->_add_font('CP932', 'ＤＦＰ勘亭流');
		$this->_add_font('CP932', 'ＤＦＰ極太明朝体');
		$this->_add_font('CP932', 'ＤＦＰ細丸ゴシック体');
		$this->_add_font('CP932', 'ＤＦＰ行書体');
		$this->_add_font('CP932', 'ＤＦＰ超極太明朝体');
		$this->_add_font('CP932', 'ＤＦＰ隷書体');
		$this->_add_font('CP932', 'ＤＦＰ麗雅宋');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=57AE8B1BD83076977E0CFCBF30BC593F
		$this->_add_font('CP932', 'HGP行書体');
		$this->_add_font('CP932', 'HGS創英角ｺﾞｼｯｸUB');
		$this->_add_font('CP932', 'HG創英角ｺﾞｼｯｸUB');
		$this->_add_font('CP932', 'HG正楷書体-PRO');
		$this->_add_font('CP932', 'ふみゴシック');
		$this->_add_font('CP932', '富士ポップＰ');
		$this->_add_font('CP932', '有澤太楷書');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=2DC946C4BF9B062677E0B0AB351BEAD1
		$this->_add_font('CP932', 'HGP創英角ｺﾞｼｯｸUB');
		$this->_add_font('CP932', 'HG丸ｺﾞｼｯｸM-PRO');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=C218592238965162DA24807013773487
		$this->_add_font('CP932', 'AR P教科書体M');
		$this->_add_font('CP932', 'AR古印体B');
		$this->_add_font('CP932', 'HG行書体');

		# http://r1.local:8080/dbtest.php?do=analyze&md5=8F6410FDD9F058C89BC39CDF9CC6934C
		$this->_add_font('CP1251', 'DS Goose'); # cyrillic high chars
		$this->_add_font('CP1251', 'FreeSet'); # cyrillic high chars
	}

	function _add_font($cs, $fn)
	{
		$fn_orig = $fn;
		$debug = 0;

		# 1. encode as original charset
		$fn = iconv('UTF-8', $cs, $fn);
		if ($debug) echo '1/2[',$fn_orig,']=>[',htmlspecialchars($fn),']<br>';

		# 2. interpret as display charset
		# NOTE: seems like analyzer doesn't use //ignore or //translit so we shouldn't either
		# we might get an empty string back (usually on MB charsets) but that's ok
		$fn = @iconv($this->display_cs, 'UTF-8', $fn);
		if ($debug) echo '2/2[',$fn_orig,']=>[',htmlspecialchars($fn),']<br>';

		if (!$fn)
		{
			if ($debug) echo 'w: ignored font "',htmlspecialchars($fn),'" (',$fn_orig,') due to iconv problem<br>';
			return;
		}

		$this->fonts[$fn] = $cs;
	}

	# fn: font name in original charset, bytes interpreted as CP1252, converted to UTF-8
	function put_font_name($fn)
	{
		$fn = rtrim($fn, "\n");
		$fn = urldecode($fn); # spaces

		if (array_key_exists($fn, $this->fonts))
		{
			$cs = $this->fonts[$fn];
			# shortcut: ignore ones that'd suggest the current charset
			# (but make sure the font isn't added to misses)
			if ($cs === $this->display_cs)
				return;
			if (!array_key_exists($cs, $this->matches))
				$this->matches[$cs] = 0;
			$this->matches[$cs]++;
		}
		else
		{
			if (!array_key_exists($fn, $this->misses))
				$this->misses[$fn] = true;
		}
	}

	function get_likely_charsets()
	{
		if (empty($this->matches))
			return null;

		$maxval = 0;
		foreach ($this->matches as $cs => $cnt)
		{
			if ($cnt > $maxval)
				$maxval = $cnt;
		}

		$best = [];
		foreach ($this->matches as $cs => $cnt)
		{
			if ($cnt === $maxval)
				$best[] = $cs;
		}
		sort($best);

		return $best;
	}

	function get_unknown_fonts()
	{
		$rv = [];
		foreach ($this->misses as $fn => $cnt)
			$rv[] = $fn;
		sort($rv);
		return $rv;
	}
}

function render_analyze()
{
	# http://r1.local:8080/dbtest.php?do=analyze&md5=9992904C0FCB861ED3E625E84C85E5D2
	ini_set('max_execution_time', 10); # sec

	db_init();

	$md5 = decode_md5(@strval($_GET['md5']));

	if (!$md5)
	{
		http_response_code(400);
		dbtest_write_header('analyze - dbtest');
		echo '<p><b>Error:</b> invalid md5';
		return;
	}

	$row = db_fetch_first_row("
	SELECT fm.data_filename
	FROM f_reposts_meta AS fm
	WHERE fm.md5=?
	", [[SQLITE3_BLOB, $md5]]);

	if (!$row)
	{
		http_response_code(404);
		dbtest_write_header('analyze - dbtest');
		echo '<p><b>Error:</b> md5 not found in database';
		return;
	}

	$targz = get_data_targz($row['data_filename']);
	$tardb = get_data_db($row['data_filename']);

	$tarpath = get_data_tarpath($row['data_filename']);

	$tarrow = get_tar_file_info($tardb, $tarpath);
	if (!$tarrow)
	{
		http_response_code(500);
		dbtest_write_header('analyze - dbtest');
		echo '<p><b>Error:</b> local file missing, unable to analyze';
		echo '<pre style="color: dimgray; white-space: pre-wrap;">';
		echo 'tardb=',$tardb,"\n";
		echo 'tarpath=',$tarpath,"\n";
		$nums = preg_replace('/\..*/', '', $row['data_filename']);
		echo "% sqlite3 -readonly $tardb \"SELECT filename FROM files WHERE filename GLOB '*$nums*';\"";
		echo '</pre>';
		return;
	}
	$offset = $tarrow['offset'];
	$size = $tarrow['filesize'];
	$datamd5 = $tarrow['datamd5'];

	$cs = get_valid_charset();

	# -md5=$datamd5
	# ^ maybe meaningless, it can't even signal the error and just wastes cpu
	$cmd = read_db_exe." -tar=$targz -db=$tardb -offset=$offset -size=$size | ".
		analyze_exe." -charset=$cs -nogc /dev/stdin";

	http_response_code(200);
	dbtest_write_header('analyze - dbtest');

	#
	# html crap
	#

	$charsets = [
		[null, "Windows-125x"],
		["CP1250", "Latin-2; Central European"],
		["CP1251", "Cyrillic"],
		["CP1252", "Latin-1; Western European"],
		["CP1253", "Greek"],
		["CP1254", "Turkish"],
		["CP1255", "Hebrew"],
		["CP1256", "Arabic"],
		["CP1257", "Baltic"],
		["CP1258", "Vietnamese"],
		[null, "Eastern Asian"],
		["CP874", "Thai"],
		["CP932", "Shift JIS; Japanese"],
		["CP936", "GBK; Simplified Chinese"],
		["CP949", "Unified Hangul Code; Korean"],
		["CP950", "Big5; Traditional Chinese"],
	];
	echo
		'<form>'.
		'<input type="hidden" name="do" value="analyze">'.
		'<input type="hidden" name="md5" value="',strtoupper(bin2hex($md5)),'">'.
		'<p>'.
		'<label>'.
		'Charset:<sup title="Used to decode non-Unicode text in SWF1-5.">(?)</sup> '.
		'<select name="charset">';
	foreach ($charsets as $i => $t)
	{
		if ($t[0])
		{
			$sel = ($t[0] == $cs) ? ' selected' : '';
			echo '<option value="',$t[0],'"',$sel,'>',$t[0],' (',$t[1],')</option>';
		}
		else
		{
			if ($i) echo '</optgroup>';
			echo '<optgroup label="',$t[1],'">';
		}
	}
	echo
		'</optgroup>'.
		'</select>'.
		'</label>'.
		' '.
		'<input type="submit" value="Apply">'.
		' '.
		'<span id="localehint"></span>'.
		'</form>'.
		'<p>'.
		'See also: '.
		'<a href="?do=md5info&md5=',strtoupper(bin2hex($md5)),'">md5info</a>'.
		'<style>
td { font-family: monospace; }
td:first-child { white-space: pre; font-weight: bold; }
td:last-child { white-space: pre-wrap; }
td.e { color: red; font-weight: bold; } /* errorish */
td.i { font-weight: bold; } /* warning-ish */
sup,span[title] { cursor: help; border-bottom: 1px dotted currentColor; }
</style>';

	if ($cs === 'CP932')
	{
		# https://www.palm84.com/entry/20170422/1492866798
		echo '<style>'.
			'td{'.
				'font:16px MS PGothic;'.
				'line-height:18px;'.
				'letter-spacing:0;'.
			'}'.
		'</style>';
	}

	$ld = null;

	echo '<table border>';
	$p = popen($cmd, 'rb');
	while ($line = fgets($p))
	{
		# NOTE: line contains a trailing newline

		if ($line[0] === '!')
		{
			$sp = strpos($line, ' ');
			if ($sp !== false)
			{
				# "!background-color #ffffff"
				$p1 = substr($line, 1, $sp-1); # "background-color"
				$p2 = substr($line, $sp+1);    # "#ffffff"
				echo "<tr><td>$p1<td>";
				urldecode2($p2);

				if ($ld && $p1 === 'font-name')
					$ld->put_font_name($p2);
			}
			else
			{
				# ???
				echo '<tr>';
				echo '<td>';
				echo '<td>';
				urldecode2($line);
			}
			continue;
		}

		if (str_starts_with($line, '#! '))
		{
			# error-ish line
			# "#! 000006fd DefineText<11>: font 2 has no glyphs defined"
			# http://r1.local:8080/dbtest.php?do=analyze&md5=4EE1F1D36327255762B616C892D717ED
			# not urlencoded, can contain html-unsafe characters
			echo '<tr><td><td class="e">';
			echo htmlspecialchars(substr($line, 3));
			continue;
		}

		if (str_starts_with($line, '# '))
		{
			# warning-ish line
			# "# overflow reading tag code and length (bytes=<00>)"
			# http://r1.local:8080/dbtest.php?do=analyze&md5=4EE1F1D36327255762B616C892D717ED
			# not urlencoded, can contain html-unsafe characters
			# forgot how these should be styled
			echo '<tr><td><td class="i">';
			echo htmlspecialchars(substr($line, 2));
			continue;
		}

		$a = explode(' ', rtrim($line, "\n"));
		switch ($a[0])
		{
			# meaningless with stdin input
			# "file 0 /dev/stdin"
			case 'file':
				break;

			case 'swf-header':
			{
				# https://blog.m2osw.com/swf_tag_file_header
				# size: uncompressed version of file

				switch ($a[2])
				{
					case '1':
					case '2':
					case '3':
					case '4':
					case '5':
						$ld = new LocaleDetect(get_valid_charset());
				}

				echo '<tr>';
				echo '<td>swf-header';
				echo '<td>';
				echo "magic={$a[1]}";
				echo " version={$a[2]}";
				echo " size={$a[3]}";
				break;
			}

			case 'movie-header':
			{
				# https://blog.m2osw.com/swf_tag_file_header

				$mindim = min(intval($a[2]), intval($a[3]), intval($a[4]), intval($a[5]));
				$maxdim = max(intval($a[2]), intval($a[3]), intval($a[4]), intval($a[5]));
				if ($mindim < 0)
				{
					$maxdim = max((-$mindim) << 1, $maxdim);
				}
				$maxbits = ceil(log($maxdim, 2))+1;

				# TODO: correctly parse negative fps (silly and meaningless)
				sscanf($a[6], "%02x-%02x", $b1, $b2);
				$fps = $b1 / 256.0 + $b2;
				$fps = sprintf("%f", $fps);

				echo '<tr>';
				echo '<td>movie-header';
				echo '<td>';
				echo "dpybits={$a[1]}";
				echo " [fit: {$maxbits}]";
				echo " xmin={$a[2]}";
				echo " xmax={$a[3]}";
				echo " ymin={$a[4]}";
				echo " ymax={$a[5]}";
				echo " fps=&lt;{$a[6]}&gt;";
				echo " [{$fps}]";
				echo " frames={$a[7]}";
				break;
			}

			case 'zlib-header':
			{
				# https://www.rfc-editor.org/rfc/rfc1950

				# get all valid checksum values
				# uh, can there even be more than one
				$valids = [];
				for ($i = 0; $i <= 31; $i++)
				{
					# method, info
					$b1 = intval($a[1]) | intval($a[2]) << 4;
					# check, usedict, level
					$b2 = $i | ($a[4]=='t'?1:0) << 5 | intval($a[5]) << 6;
					if (($b1*256+$b2) % 31 == 0)
						$valids[] = $i;
				}

				echo '<tr>';
				echo '<td>zlib-header';
				echo '<td>';
				echo "method={$a[1]}";
				echo " info={$a[2]} [0-7]";
				echo " check={$a[3]}";
				echo ' [valid: ',implode(' ', $valids),']';
				echo " usedict={$a[4]}";
				echo " level={$a[5]} [0-3]";
				echo " dict={$a[6]}"; # %08x if present
				break;
			}

			case 'swf-data-total':
			{
				# size: movie header and parsed part of tag stream (<= end tag, < overflow)
				echo '<tr>';
				echo '<td>',$a[0];
				echo '<td>';
				echo "size={$a[1]}";
				echo " crc={$a[2]}";
				break;
			}

			# main.d printEndOfFile
			# getUnusedSwfData
			# included in header size but unused (reached end tag, i think that's the only cause?)
			case 'swf-junk-data': # unused tag stream data (compressed, no end tag)
			case 'swe-junk-data': # unused tag stream data (compressed, with end tag)
			case 'unc-junk-data': # unused tag stream data (uncompressed, no end tag)
			case 'une-junk-data': # unused tag stream data (uncompressed, with end tag)
			# getOverflowSwfData
			case 'ovf-junk-data': # tag data that was ignored due to an overflow
			# getCompressedJunkData (rare)
			case 'cmp-junk-data': # junk data inside compressed body
			# getEofJunkData
			case 'eof-junk-data': # junk data at end of file
			{
				$whatisit = [
					'swe-junk-data' =>
					'included in the header size but unused due to reaching an end tag (compressed file)',
					# http://r1.local:8080/dbtest.php?do=analyze&md5=BC1641C01A983B2ED453E3F367D80D74
					'une-junk-data' =>
					'included in the header size but unused due to reaching an end tag (uncompressed file)',
					'eof-junk-data' =>
					'unused data at the end of the file',
				];
				$alt = array_key_exists($a[0], $whatisit) ? ' title="'.$whatisit[$a[0]].'"' : '';
				echo '<tr>';
				if ($alt)
					echo '<td><span',$alt,'>',$a[0],'</span>';
				else
					echo '<td>',$a[0];
				echo '<td>';
				echo "size={$a[1]}";
				echo " crc={$a[2]}";
				echo " bytes={$a[3]}";
				break;
			}

			default:
			{
				# mystery
				echo '<tr>';
				echo '<td>';
				echo '<td>';
				echo htmlspecialchars($line);
				break;
			}
		}
	}
	echo '</table>';

	if ($ld)
	{
		$sug = $ld->get_likely_charsets();
		if ($sug)
		{
			echo '<script>'.
				'localehint.textContent="Hint: try ',implode(', ', $sug),'"'.
			'</script>';
		}

		$unk = $ld->get_unknown_fonts();
		if (!empty($unk))
		{
			echo '<pre style="color: dimgray;">';
			echo 'Non-locale-specific fonts (enc):',"\n";
			foreach ($unk as $fn)
			{
				echo '- ',htmlspecialchars($fn),"\n";
			}
			echo '</pre>';
		}
	}
	else
	{
		echo '<script>'.
			'localehint.textContent="Note: This flash is fully Unicode, charsets are not used."'.
		'</script>';
	}

	echo
		'<pre style="white-space: pre-wrap; color: dimgray;">',
		$cmd,
		'</pre>';
}

# idea: strpos %, write chunks? at least detect if there are any %? get start pos from it?
function urldecode2($s)
{
	for ($i = 0; $i < strlen($s); $i++)
	{
		if ($s[$i] !== '%')
		{
			switch ($s[$i])
			{
				case '&':
					echo '&amp;';
					break;
				case '<':
					echo '&lt;';
					break;
				case '>':
					echo '&gt;';
					break;
				default;
					echo $s[$i];
					//~ echo '[',$s[$i],']';
					//~ echo '[',ord($s[$i]),']';
			}
		}
		else
		{
			$n1 = ord($s[$i+1]);
			$n2 = ord($s[$i+2]);
			$b = 0;
			# 0=48, 9=57, a=97
			$b |= (($n1 < 97) ? $n1-48 : (10+($n1-97))) << 4;
			$b |= (($n2 < 97) ? $n2-48 : (10+($n2-97)));
			echo chr($b);
			//~ echo sprintf("[%02x]", $b);
			//~ echo sprintf("[%01d.%01d]", $n1, $n2);
			//~ echo sprintf("[%s.%s]", $s[$i+1], $s[$i+2]);
			$i += 2;
		}
	}
}
