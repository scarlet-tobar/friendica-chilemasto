{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}

{{* Display system messages *}}
{{if $notices}}
	{{foreach $notices as $notice}}
		<div class="alert alert-warning" role="alert">{{$notice}}</div>
	{{/foreach}}
{{/if}}

<form id="login-form" action="{{$dest_url}}" role="form" method="post">
	<div id="login-group" role="group" aria-labelledby="login-head">
		<input type="hidden" name="auth-params" value="login" />

		<div id="login-head"><h1>{{$login}}</h1></div>

		<div id="login_standard">
			<div id="id_{{$lname.0}}_wrapper" class="form-group field input">
				<input id="id_{{$lname.0}}" class="form-control" name="{{$lname.0}}" placeholder="{{$lname.1}}" autofocus></input>
				{{if $lname.3}}
					<span class="help-block" id="{{$lname.0}}_tip" role="tooltip">{{$lname.3 nofilter}}</span>
				{{/if}}
			</div>
			<div id="id_{{$lpassword.0}}_wrapper" class="form-group field input">
				<input id="id_{{$lpassword.0}}" class="form-control" name="{{$lpassword.0}}" placeholder="{{$lpassword.1}}" autofocus type="password"></input>
				{{if $lpassword.3}}
					<span class="help-block" id="{{$lpassword.0}}_tip" role="tooltip">{{$lpassword.3 nofilter}}</span>
				{{/if}}
			</div>
			<div id="login-end"></div>
			<div id="login-lost-password-link">
				<a href="lostpass" id="lost-password-link">{{$lostlink}}</a>
			</div>
		</div>

		{{if $openid}}
		<div id="login_openid">
			{{include file="field_openid.tpl" field=$lopenid}}
		</div>
		{{/if}}

		{{include file="field_checkbox.tpl" field=$lremember}}

		<button type="submit" name="submit" id="login-submit-button" class="btn btn-primary" value="{{$login}}">{{$login}}</button>

		{{foreach $hiddens as $k=>$v}}
			<input type="hidden" name="{{$k}}" value="{{$v}}" />
		{{/foreach}}

		<div id="login-end"></div>
	</div>
</form>

{{if $register}}
<hr>
<div id="login-extra-links">
	<p id="new-here">{{$new}}</p>
	<h3 id="login-head" class="sr-only">{{$register.title}}</h3>
	<a href="{{$register.url}}" id="register-link" class="btn btn-default">{{$register.title}}</a>
</div>
{{/if}}
