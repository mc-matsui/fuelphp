<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="">
<meta name="author" content="">
<title>fuelphp_BBS</title>
<link href="//maxcdn.bootstrapcdn.com/bootswatch/3.2.0/simplex/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script src="https://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->
</head>
<body>
	<div class="container">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h4>FuelPHP掲示板</h4>
			</div>
			<div class="panel-body">
				<?php
				//例外の場合の処理
				if (@$save)
				{
					print "<p class=\"alert alert-success\">" . $save . "</p>";
				}

				print $mem_version;

				print Session::get_flash('error'); //エラー文表示
				print Session::get_flash('success'); //投稿結果表示

				//<form>開始
				print Form::open(array('action' => 'bbs/index', 'method' => 'post'));
				?>
					<div class="form-grop">
						<?php
						print Form::label("名前", "name");
						print Form::input("name", Input::post("name"), array('class' => 'form-control'));
						?>
					</div>
					<div class="form-grop">
						<?php
						print Form::label("メールアドレス", "email");
						print Form::input("email", Input::post("email"), array('class' => 'form-control'));
						?>
					</div>
					<div class="form-grop">
						<?php
						print Form::label("カテゴリ", "item")."<br>";
						print Form::radio("item",1, true);
						print Form::label(1, "item");
						print Form::radio("item",2);
						print Form::label(2, "item");
						print Form::radio("item",3);
						print Form::label(3, "item");
						?>
					</div>
					<div class="form_group">
						<?php
						print Form::label("内容", "message");
						print Form::textarea("message", Input::post("message"), array('class' => 'form-control','rows' => 3));
						?>
					</div>
					<?php
					//CSRF対策のトークン取得
					print  Form::hidden($token_key, $token);
					print  Form::submit("submit","投稿", array('class' => 'btn btn-default'));
				print  Form::close();
				//</form>終了
				?>
			</div>
		</div>
		<p><a href=?reset=1>ソートの解除</a></p>
		<hr>
		<div>
			<p>★カテゴリ数ランキング★</p>
			<?php
				//redisで実装したランキング表示
				print $itemCount;
			?>
		</div>
		<hr>
		<?php
		foreach ($posts as $post)
		{
			print "<div>";
				print "<h4>" . $post->name . "</h4>";
				print "<p>" . $post->email . "</p>";
				print "<p><a href=\"?item=" . $post->item . "\">" . $post->item . "</a></p>";
				print "<p>" . nl2br($post->message) . "</p>";
			print "</div><hr>";
		}
		//Pagination(ページャー)を表示する
		print Pagination::instance("mypagination")->render();
		?>
	</div> <!-- /container -->
</body>
</html>