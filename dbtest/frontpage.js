var highlighted_link = null;

var clear_highlights = function ()
{
	if (highlighted_link !== null)
	{
		highlighted_link.className = highlighted_link.className.replace("highlight-link", "");
		highlighted_link = null;
	}
};

var set_highlight_link = function (id)
{
	clear_highlights();
	var link = document.getElementById("link"+id);
	link.className += " highlight-link";
	highlighted_link = link;
};

var set_highlight_thumb = function (id)
{
	clear_highlights();
	var link = document.getElementById("thumb"+id);
	link.className += " highlight-link";
	highlighted_link = link;
};

onkeydown = function ()
{
	switch (event.code)
	{
		case "ArrowRight":
			history.forward();
			onbeforeunload = function () {
				clearTimeout(t);
			};
			var t = setTimeout(function () {
				onbeforeunload = null;
				document.getElementById("refresh_link").click();
			}, 50);
			break;
		case "ArrowLeft":
			history.back();
			break;
	}
};

var load_script = function (id, url, cb)
{
	var script = document.getElementById(id);
	if (script)
	{
		if (script.dataset.loaded)
		{
			cb();
			return;
		}
		else
		{
			script.addEventListener("load", cb);
			return;
		}
	}
	else
	{
		var script = document.createElement("script");
		script.id = id;
		script.src = url;
		script.addEventListener("load", function () {
			script.dataset.loaded = "loaded";
			cb();
		});
		document.documentElement.appendChild(script);
	}
};

// https://stackoverflow.com/questions/768268/how-to-calculate-md5-hash-of-a-file-using-javascript
// https://github.com/satazor/js-spark-md5
// https://developer.mozilla.org/en-US/docs/Web/API/HTML_Drag_and_Drop_API/File_drag_and_drop
goto_drop_handler = function ()
{
	event.target.classList.remove("dragging");
	event.preventDefault();

	if (!event.dataTransfer.files.length)
	{
		console.log("drop event contains no files");
		return;
	}

	var input = event.target;
	var funcs;
	if (input.matches("input[type=\"submit\"]"))
	{
		// bleh, i can't get it to work on the submit button
		funcs = {
			begin: function () {
				input.value = "...";
			},
			progress: function (bytes) {
				input.value = Math.round((bytesRead / file.size) * 100.0) + "%";
			},
			result: function (md5) {
				console.log(md5);
			},
			end: function () {
				input.value = "Go to";
			},
		};
	}
	else if (input.matches("input[type=\"search\"]"))
	{
		funcs = {
			begin: function () {
				input.value = "";
				input.placeholder = "hashing...";
			},
			progress: function (bytes) {
				input.placeholder = "hashing... " + Math.round((bytesRead / file.size) * 100.0) + "%";
			},
			result: function (md5) {
				input.value = md5;
			},
			end: function () {
				input.placeholder = "";
			},
		};
	}
	else
	{
		console.log("???");
		return;
	}
	funcs.begin();

	var file = event.dataTransfer.files[0];
	console.log(file);
	var reader = file.stream().getReader();
	var bytesRead = 0;

	load_script("spark-md5", DBTEST_DIR_PUBLIC+"/spark-md5.js", function ()
	{
		var md5 = new SparkMD5.ArrayBuffer();
		var loop = function ()
		{
			return reader.read()
				.then(function (t)
				{
					if (t.done)
					{
						return;
					}
					md5.append(t.value);
					bytesRead += t.value.length;
					funcs.progress(bytesRead);
					// don't block the event loop
					return new Promise(function (resolve) {
						var img = new Image;
						img.src = "";
						img.onerror = resolve;
					}).then(loop);
				});
		};
		loop()
			.then(function ()
			{
				funcs.result(md5.end());
			})
			.finally(function ()
			{
				funcs.end();
			});
	});
};

goto_dragenter_handler = function ()
{
	event.target.classList.add("dragging");
	event.preventDefault();
	return false;
};

goto_dragleave_handler = function ()
{
	event.target.classList.remove("dragging");
	event.preventDefault();
	return false;
};
