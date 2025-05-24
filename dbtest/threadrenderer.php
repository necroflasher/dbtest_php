<?php

# fixme: spoilers don't work
# want: handle board links like >>>/a/, >>>/a/123, adjust the existing preg_replace for it - sample in http://127.1.1.1/dbtest.php?do=md5info&md5=7DB5714FB62CB8932DD2C212628AA232

#
# Renders a set of comments that belong to the same thread.
#
class ThreadRenderer
{
	# what columns to fetch from f_comments
	const comment_columns_sql = '
		postnum,
		subnum,
		timestamp,
		deleted,
		name,
		tripcode,
		email,
		subject,
		comment';

	function __construct()
	{
		$this->postnum_alias = [];
		$this->op_postnum = null;
		$this->subject = null;
		$this->num_replies = 0; # posts excluding op
		$this->num_comments = 0; # posts with text including op
	}

	function render_post($postrow, $postidx)
	{
		$postnum   = $postrow['postnum'];   # int >=1
		$subnum    = $postrow['subnum'];    # int >=0
		$timestamp = $postrow['timestamp']; # "2022-12-31 23:59:59"
		$deleted   = $postrow['deleted'];   # "2022-12-31 23:59:59"
		$name      = $postrow['name'];      # text
		$tripcode  = $postrow['tripcode'];  # text
		$email     = $postrow['email'];     # text
		$subject   = $postrow['subject'];   # text
		$comment   = $postrow['comment'];   # text

		$post_id = null;
		if ($subnum)
		{
			$post_id = "$postnum,$subnum";
			$this->postnum_alias[$post_id] = ($postidx+1).'+';
		}
		else
		{
			$post_id = "$postnum";
			$this->postnum_alias[$post_id] = $postidx+1;
		}

		if ($postidx)
		{
			$this->num_replies += 1;
		}
		else
		{
			$this->op_postnum = $postnum;
			$this->subject = $subject;
		}

		#
		# begin post
		#
		echo
			'<div class="comment-container" id="com',$post_id,'">'.
			'<span class="comment-sidearrows">&gt;&gt;</span>'.
			'<pre class="comment-body">';

		#
		# info line
		#
	
		echo '<span class="info-line" title="No. ',$post_id,' - posted ',$timestamp;
		if ($deleted)
		{
			echo ' - deleted ',$deleted;
		}
		echo '">';

		if ($email)
		{
			# open A
			echo '<a href="mailto:',htmlspecialchars($email),'">';
		}
		switch ($name)
		{
			case 'Anonymous':
				echo '<b>Anonymous</b>';
				break;
			case '': # tripcode
				break;
			default:
				echo '<b>',htmlspecialchars($name),'</b>';
		}
		if ($tripcode)
		{
			echo $tripcode;
		}
		if ($email)
		{
			# close A
			echo '</a>';
		}
		echo ' No. ',($postidx+1);
		if ($subnum)
		{
			echo '+';
		}
		if ($deleted)
		{
			echo ' (deleted)';
		}
		echo '</span>';

		if ($comment)
		{
			$this->num_comments += 1;

			$link_replace_fn = function ($m) {
				$postnum_str = $m[1];
				if ($a = $this->postnum_alias[$postnum_str] ?? false)
				{
					return "<a href=\"#com$postnum_str\" class=\"quotelink\">&gt;&gt;$a</a>";
				}
				else
				{
					return "<a href=\"?do=goto&to=$postnum_str\" class=\"quotelink crossthread\">&gt;&gt;$postnum_str</a>";
				}
			};

			$lines = explode("\n", $comment);
			$linecount = count($lines);

			$blanks = 0;
			foreach ($lines as $i => $line)
			{
				if (!$line)
				{
					$blanks++;
					continue;
				}

				$gtpos = strpos($line, '>');
				$colonpos = strpos($line, ':');

				echo '<div class="';
				if ($blanks)
				{
					# 2: http://127.1.1.1/dbtest.php?do=md5info&md5=CEC6FC2CE298982015CF8765ECD6152E
					if ($blanks === 1)
					{
						echo ' blankline';
					}
					else
					{
						echo ' twoblanklines';
					}
					$blanks = 0;
				}
				if ($gtpos === 0)
				{
					echo ' greentext';
				}
				if ($i === $linecount-1)
				{
					echo ' lastline';
				}
				echo '">';

				$line = htmlspecialchars($line);
				if ($i === $linecount-1 && $line[0] == '[')
				{
					# http://127.1.1.1/dbtest.php?do=md5info&md5=D24CE0DC67A47E14325B1F1DB33EDF8B
					$line = preg_replace(
						'/^\[fortune color=&quot;(#[0-9a-f]{6})&quot;\](.+)\[\/fortune]$/',
						'<span style="color: \1;">\2</span>',
						$line);
				}
				if ($gtpos !== false)
				{
					$line = preg_replace_callback('/&gt;&gt;([,0-9]+)/', $link_replace_fn, $line);
				}
				if ($colonpos !== false)
				{
					$line = preg_replace(
						'/\bhttps?:[^ <>]+/',
						'<a href="\0" target="_blank" rel="noreferrer">\0</a>',
						$line);
				}

				echo
					$line,
					'</div>';
			}
		}
		else
		{
			echo
				'<div class=" lastline">'.
					'<span class="no-comment-placeholder">(no comment)</span>'.
				'</div>';
		}

		#
		# end post
		#
		echo
			'</pre>'.
			'</span>'.
			'</div>';
	}
}
