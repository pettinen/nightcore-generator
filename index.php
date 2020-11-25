<?php
define('CACHE', true);
define('PRESETS', [
	1 => ['speed' => '1.4', 'pitch' => '+300', 'bass' => '+4'],
	2 => ['speed' => '1.35', 'pitch' => null, 'bass' => '+4',
		'comment' => 'less chipmunky'],
	3 => ['speed' => '1.5', 'pitch' => '+600', 'bass' => '+8',
	'comment' => '<span class="latin">t</span>&#822;&#779;&#827;<span class="latin">h</span>&#823;&#778;&#861;&#839;&#858;<span class="latin">o</span>&#824;&#836;&#799;<span class="latin">s</span>&#821;&#864;&#834;&#815;&#857;&#852;<span class="latin">e</span>&#824;&#784;&#840; &#824;&#788;&#861;&#836;&#853;&#857;&#815;<span class="latin">s</span>&#824;&#838;&#838;&#861;&#827;&#790;<span class="latin">t</span>&#822;&#781;&#794;&#797;&#817;&#793;<span class="latin">i</span>&#821;&#784;&#845;&#825;<span class="latin">l</span>&#822;&#781;&#788;&#850;&#845;&#798;<span class="latin">l</span>&#822;&#833;&#773;&#806;&#839;&#793; &#822;&#768;&#799;<span class="latin">l</span>&#823;&#774;&#796;<span class="latin">i</span>&#823;&#829;&#836;&#775;&#852;&#790;&#853;<span class="latin">v</span>&#820;&#768;&#837;&#852;&#818;<span class="latin">i</span>&#822;&#769;&#773;&#843;&#800;<span class="latin">n</span>&#822;&#778;&#778;&#787;&#793;<span class="latin">g</span>&#821;&#842;&#844;&#793;&#860; &#821;&#779;&#846;<span class="latin">s</span>&#821;&#786;&#827;<span class="latin">h</span>&#821;&#773;&#837;&#800;&#798;<span class="latin">a</span>&#822;&#773;&#842;&#829;&#817;&#840;<span class="latin">l</span>&#821;&#768;&#809;&#811;&#799;<span class="latin">l</span>&#824;&#856;&#789;&#776;&#815;&#860; &#820;&#779;&#831;&#825;&#816;<span class="latin">e</span>&#821;&#850;&#836;&#791;<span class="latin">n</span>&#823;&#788;&#842;&#787;&#827;&#816;&#790;<span class="latin">v</span>&#820;&#779;&#771;&#788;&#839;&#792;<span class="latin">y</span>&#822;&#835;&#771;&#852;&#841;&#857; &#821;&#859;&#817;&#797;&#853;<span class="latin">t</span>&#823;&#774;&#855;&#833;&#805;&#807;&#816;<span class="latin">h</span>&#824;&#783;&#855;&#826;<span class="latin">e</span>&#822;&#768;&#831;&#860;&#839; &#820;&#773;&#832;&#772;&#857;<span class="latin">d</span>&#820;&#835;&#864;&#772;&#796;<span class="latin">e</span>&#823;&#849;&#829;&#775;&#840;&#800;&#839;<span class="latin">a</span>&#822;&#780;&#795;&#817;&#846;&#803;<span class="latin">d</span>&#822;&#778;&#844;&#768;&#799;']
	]);
define('DEFAULT_PRESET', 1);

$error = null;

function sox_effects($preset, $html=false) {
	$process = function($effect, $default) use ($preset, $html) {
		if (!$html && !isset($preset[$effect]))
			return [];
		$value = $preset[$effect] ?? $default;
		if (!$html)
			$value = escapeshellarg($value);
		return [$effect, $value];
	};
	$effects = implode(' ', array_merge(
		$process('speed', '1.0'),
		$process('pitch', '±0'),
		$process('bass', '±0')
	));
	if ($html)
		$effects = htmlspecialchars($effects);
	return $effects;
}

$url = isset($_GET['v']) ? $_GET['v'] : null;
while (is_array($url))
	$url = reset($url);
$url = trim($url);

$preset_id = DEFAULT_PRESET;
if (isset($_GET['preset'])) {
	while (is_array($_GET['preset']))
		$_GET['preset'] = reset($_GET['preset']);
	$_GET['preset'] = intval($_GET['preset']);
	if (isset(PRESETS[$_GET['preset']]))
		$preset_id = $_GET['preset'];
	else
		$error = 'invalid preset; valid ones are '
			. implode(', ', array_keys(PRESETS));
}
$preset = PRESETS[$preset_id];

if ($url !== '') {
	$pattern = '#^((https?://)?(.+\.)?(youtube\.com/watch\?(.*&)?v=|youtu\.be/))?([\w-]{10,})#';
	if (preg_match($pattern, $url, $matches) !== 1)
		die("couldn't make sense of your link");

	$id = $matches[6];
	$server_id = "$id.$preset_id";
	$lockfile = ".in-progress/$server_id";
	$outfile = "files/$server_id.mp3";

	if (file_exists($lockfile)) {
		set_time_limit(60);
		while (file_exists($lockfile)) {
			if (sleep(1) !== 0)
				die('something happened');
		}
	} else if (!CACHE || !file_exists($outfile)) {
		if (!touch($lockfile) && !file_exists($lockfile))
			die('something happened');

		$id_e = escapeshellarg($id);
		$outfile_e = escapeshellarg($outfile);
		$tempfile = tempnam('/tmp', 'ytdl-');
		$tempfile_e = escapeshellarg($tempfile);

		exec("youtube-dl -q --no-continue -f bestaudio -o $tempfile_e -- $id_e", $_, $ytdl_rv);
		if ($ytdl_rv !== 0) {
			unlink($tempfile);
			unlink($lockfile);
			die("couldn't get audio from youtube. maybe the video id is invalid"
				. (strlen($id) === 11 ? ' or I need to update youtube-dl (in which case hmu)' : ''));
		}

		$sox_effects = sox_effects($preset);
		$sox_cmd = "ffmpeg -i $tempfile_e -f wav - | sox -G -q -t wav - -C -1.4 $outfile_e $sox_effects";
		exec($sox_cmd, $sox_output, $sox_rv);
		unlink($tempfile);
		unlink($lockfile);
		if ($sox_rv !== 0) {
			@unlink($outfile);
			die('something happened');
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>Lisää nightcoree</title>
  <meta property="og:title" content="nightcore generator">
  <meta property="og:description" content="generates nightcore">
  <meta property="og:type" content="website">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" integrity="sha256-l85OmPOjvil/SOvVt3HnSSjzF1TUMyT9eV0c2BzEGzU=" crossorigin="anonymous">
  <link rel="stylesheet" href="style.css">
<?php if (!$error): ?>
  <style>
    #error {
      display: none;
    }
  </style>
<?php endif; ?>
</head>
<body>
<div id="error">
  <span id="error-text"><?php echo $error; ?></span>
  <button type="button" id="hide-error">&#x2573;</button>
</div>
<div class="container">
  <h1>nightcore generator</h1>
  <form name="nightcore" action="">
    <label>YouTube link<br>
      <input type="text" size="30" name="v"<?php if ($url) echo " value=\"$url\""; ?>>
    </label>
    <div id="submit-buttons">
<?php foreach (PRESETS as $preset_key => $preset_val): ?>
      <button name="preset" value="<?php echo $preset_key; ?>"<?php
	echo ($url && $preset_id === $preset_key) ? ' class="current">' : '>';
	echo sox_effects($preset_val, true);
	if (isset($preset_val['comment']))
		echo "<span class=\"presetText\">($preset_val[comment])</span>";
      ?></button>
<?php endforeach; ?>
    </div>
    <span style="display: none;" id="submit-spinner"><img id="spinner" alt="Loading..." src="spinner.gif"></span>
  </form>
</div>
<div class="container">
<?php
if ($url):
?>
<div class="container">
  <audio controls autoplay>
    <source src="<?php echo $outfile; ?>">
  </audio>
  <p><a href="https://www.youtube.com/watch?v=<?php echo $id; ?>">original video</a></p>
<?php endif; ?>
  <p><a href="/bass/">bass generator</a></p>
</div>
<script>
(() => {
  'use strict';
  const id = document.getElementById.bind(document);

  document.forms.nightcore.addEventListener('submit', event => {

    if (document.forms.nightcore.v.value.length < 10) {
      event.preventDefault();
      id('error').style.display = 'flex';
      id('error-text').innerText = "video id can\u2019t be that short";
    } else {
      id('submit-buttons').style.display = 'none';
      id('submit-spinner').style.display = 'initial';
    }
  });

  id('hide-error').addEventListener('click', event => {
    id('error').style.display = 'none';
  });
})();
</script>
</body>
</html>
