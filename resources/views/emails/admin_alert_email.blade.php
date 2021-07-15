<!doctype html>
<html>
<head>
	<style type="text/css">
		html, body {
			margin: 0;
			padding: 0;
			font-family: Arial, Helvetica, sans-serif;
		}
		* {
			box-sizing: border-box;
		}
		#wrapper {
			width: 700px;
			max-width: 100%;
			margin: 0 auto;
			padding: 50px 100px;
			background: #f6fafd 0% 0% no-repeat padding-box;
		}
		img {
			width: 100%;
			max-width: 100%;
		}
		#top-logo {
			width: 90px;
			margin: 0 auto;
		}
		#content-section {
			width: 100%;
			display: block;
			margin-top: 20px;
			padding: 50px;
			box-sizing: border-box;
			background: #FFFFFF 0% 0% no-repeat padding-box;
			box-shadow: 0px 5px 15px #00000029;
		}
		#content-section h2 {
			margin-top: 0;
			margin-bottom: 0;
			font-size: 20px;
			line-height: 30px;
		}
		#content-section .spacer-img {
			display: block;
			margin-top: 30px;
			margin-bottom: 30px;
			width: 100%;
		} 
		#content-html {
			font-size: 12px;
			line-height: 21px;
		}
		#title {
			text-align: center;
		}
		#content-sectionFooter {
			font-size: 12px;
			text-align: center;
		}
		#content-sectionFooter label {
			display: block;
		}

		#social-icons {
			margin-top: 30px;
			text-align: center;
		}
		#social-icons a {
			margin: 0 7px;
			font-size: 0;
		}

		ul {
			list-style: none;
			margin-left: 0;
			padding-left: 0;
			display: flex;
    		align-items: center;
    		justify-content: center;
		}
		ul li {
			font-size: 12px;
			font-weight: bold;
			margin: 0 10px;
			display: inline-block;
		}
		ul li a {
			color: #FF473E;;
		}
		ul li span {
			display: block;
			width: 1px;
			height: 15px;
			background-color: #FF473E;
			border-radius: 500rem;
			-webkit-border-radius: 500rem;
		}
		.divider {
			width: 100%;
			height: 2px;
			background-color: #FF473E;
			margin: 30px 0;
		}
		#content-footer {
			text-align: center;
		}
		#content-footer label {
			display: block;
			font-size: 12px;
			margin-top: 30px;
			text-align: center;
		}
		#button-link {
			display: inline-block;
			margin: auto;
			width: 50%;
			height: 42px;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #ffffff;
			background-color: #FF473E;
			text-decoration: none;
			margin-top: 30px;
			font-size: 12px;
		}
		.button-link {
			margin: auto;
			color: #ffffff !important;
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div id="wrapper">
		<div id="content-section">
			<div id="top-logo">
				<img src="{{ url('logo/logo.png') }}" alt="logo"/>
			</div>
			<h2 id="title">{!! $title !!}</h2>
			<div class="divider"></div>
			<div id="content-html">
				{!! $content !!}
			</div>
			<div id="button-link">
				<a class="button-link" href="{{ config('app.site_url') }}">
					Go to Member's Portal
				</a>
			</div>
			
			<div class="divider"></div>
			<div id="content-sectionFooter">
				<label>Thanks for being a part of Casper's future.<br/><br/>Casper Association Team</label>
			</div>
		</div>
		<!-- <div style="width: 100%;">
			<ul>
				<li><a href="#">Unsubscribe</a></li>
				<li><span></span></li>
				<li><a href="#">Contact Us</a></li>
				<li><span></span></li>
				<li><a href="#">Privacy Policy</a></li>
			</ul>
		</div> -->
		<div id="content-footer">
			<!-- <label>Any terms and details that should be expressed on all emails sent by the company.
				<br/><br/>Company name<br/><br/>Company email
			</label> -->
		</div>
	</div>
</body>
</html>