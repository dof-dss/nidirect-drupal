sub vcl_recv {
    set req.backend_hint = application.backend();
    unset req.http.x-cache;
}

sub vcl_hit {
	set req.http.x-cache = "hit";
}

sub vcl_miss {
	set req.http.x-cache = "miss";
}

sub vcl_pass {
	set req.http.x-cache = "pass";
}

sub vcl_pipe {
	set req.http.x-cache = "pipe uncacheable";
}

sub vcl_synth {
	set resp.http.x-cache = "synth synth";
}

sub vcl_deliver {
	if (obj.uncacheable) {
		set req.http.x-cache = req.http.x-cache + " uncacheable" ;
	} else {
		set req.http.x-cache = req.http.x-cache + " cached" ;
	}
	# uncomment the following line to show the information in the response
	# set resp.http.x-cache = req.http.x-cache;
}
