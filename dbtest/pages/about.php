<?php

function render_about()
{
	dbtest_write_header('about - dbtest');

	echo "
	<p>
		<b>2025 note: The below information is outdated.</b>
		The site now uses more recent data and thumbnail dumps.
	<p>
		This site lets you browse flashes and comments from the 4plebs data dumps.
		That's the basic idea, but it might expand to cover more features over time.
	<p>
		The specific archive.org items in use are:
		<ul>
			<li><a href=\"https://archive.org/details/4plebs-org-data-dump-2022-01\" target=\"_blank\">4plebs.org data dump 2022-01</a> (threads and posts)
			<li><a href=\"https://archive.org/details/4plebs-org-thumbnail-dump-2022-01\" target=\"_blank\">4plebs.org thumbnail dump 2022-01</a> (thumbnails, duh)
		</ul>
		The data dump CSV contains a row for each archived post with all of the database fields for it.
		This PHP script works by querying an SQLite database generated from the CSV, which contains separate tables for a few different kinds of data extracted from it.
		The tables include:
		<ol>
			<li><code>f_reposts</code> contains a row for each instance of a flash being posted. It includes the filename and thread number.</li>
			<li><code>f_reposts_meta</code> contains details about the flash that are the same every time it's posted, for example its dimensions.</li>
			<li><code>f_comments</code> contains the text of all threads and comments. This is the largest table by far.</li>
		</ol>
	<p>
		Below are some stats about the 4plebs dumps. They're smaller than you might think.
	<p>
		<table border>
			<tr>
				<th>Statistic</th>
				<th>Size</th>
				<th>Count</th>
			</tr>
			<tr>
				<td>Extracted size of the thumbnail dump</td>
				<td>394 MiB</td>
				<td>38085 files</td>
			</tr>
			<tr>
				<td>Uncompressed size of the CSV</td>
				<td>243 MiB</td>
				<td>1162449 rows</td>
			</tr>
		</table>
	<p>
		It annoys me when websites omit this so I'll say it here: All timestamps on the site are in the UTC timezone.
	<p>
		<hr>
	<p>
		<style>
			@keyframes pop {
				0%   { transform: scaleX(0.70) scaleY(0.9); }
				25%  { transform: scaleX(1.25) scaleY(1.1); }
				50%  { transform: scaleX(1.25) scaleY(1.1); }
				100% { transform: scaleX(1.00) scaleY(1.0); }
			}
			@keyframes suck {
				0%   { transform: scaleX(1); }
				100% { transform: scaleX(0.66); }
			}
			#php:hover {
				animation-name: pop;
				animation-duration: 200ms;
				animation-timing-function: linear;
				display: inline-block;
				background: url(",DBTEST_DIR_PUBLIC,"/im/fire.gif);
				color: red;
				background-size: 100% 100%;
				font-weight: bold;
				padding: 2px 4px;
				margin-top: -2px;
			}
			#php:hover img {
				animation-name: suck;
				animation-duration: 210ms;
				animation-timing-function: linear;
				animation-iteration-count: infinite;
			}
		</style>
		<center>
			<div id=\"php\" style=\"display: inline-block; font-size: small;\">
				powered by: php<br>
				<img src=\"",DBTEST_DIR_PUBLIC,"/im/php.jpg\" style=\"width: 101px; height: auto; margin-top: 2px;\">
			</div>
			<div style=\"display: inline-block;\">
				<a href=\"?do=md5info&md5=BB32C7D20A1E4581423D044A5A3C503F\">
					<img id=\"salla\" src=\"",DBTEST_DIR_PUBLIC,"/im/sallaka.gif\" style=\"height: 95px; width: auto;\">
				</a>
			</div>
			<div style=\"display: inline-block;\">
				<a href=\"?do=md5info&md5=113c829becb001072bc66ce5b616417e\">
					<img src=\"",DBTEST_DIR_PUBLIC,"/im/reimu.gif\" style=\"height: 95px; width: auto;\">
				</a>
			</div>
			<div style=\"display: inline-block;\">
				<a href=\"?do=md5info&md5=9be70deae1ab2e3804fd5d61e26dfd40\">
					<img src=\"",DBTEST_DIR_PUBLIC,"/im/miraclemoon.gif\" style=\"height: 95px; width: auto;\">
				</a>
			</div>
		</center>
	";
}
