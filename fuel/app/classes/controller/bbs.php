<?php

class Controller_Bbs extends Controller
{

	public function action_index()
	{

		//フォームからPOST送信されたか判定※Input::method()のdefaultは'GET'
		if (Input::method() === 'POST')
		{
			//CSRF対策
			if (!Security::check_token())
			{
				//Response::redirect('http://localhost/fuelphp/bbs/index');
				//本番
				Response::redirect('http://dev3.m-craft.com/matsui/mc_kadai/kadai_fuel/bbs/index');
			}
		}

		//ビューに渡すデータの配列を初期化
		$data = array();
		//CSRFトークン発行
		$data['token_key'] = Config::get('security.csrf_token_key');
		$data['token'] = Security::fetch_token();

		//入力チェックのためのvalidationオブジェクト
		$val = Validation::forge();

		//名前を入力必須、入力上限を15文字までにする
		$val->add("name","名前")
			->add_rule("required")
			->add_rule("max_length" , 15);

		//メールアドレスを入力必須、正しいメールアドレス形式かチェック
		$val->add("email","メールアドレス")
			->add_rule("required")
			->add_rule("valid_email")
			->add_rule("max_length" , 50);

		//メッセージを入力必須、入力上限を200文字までにする
		$val->add("message","内容")
			->add_rule("required")
			->add_rule("max_length" , 200);

		//フォームからPOST送信されたか判定
		if (Input::method() === 'POST')
		{
			//Validationチェックが実行(成功) and ビューから送られてくるセキュリティー用トークンをチェック
			if($val->run())
			{
				/*-----------------------------------
				 * postされた各データをDBに保存
				----------------------------------*/
				//各送信データを配列
				$props = array(
						"name"		=> Input::post("name"),
						"message"	=> Input::post("message"),
						"email"		=> Input::post("email"),
						"ip"		=> Input::real_ip(),
				);

				//モデルオブジェクト作成
				$new = Model_Mybbs::forge($props);

				//データ保存する
				if (!$new -> save())
				{
					//保存失敗
					$data["save"] = "投稿できませんでした";
				}
				else
				{
					//保存成功
					$data["save"] = "投稿しました";
				}
			}
			else
			{
				// バリデーションNGの場合
				Session::set_flash('error', $val->show_errors());
			}	//$val->run()ここまで
		}

		//ValidationオブジェクトをViewに渡す
		$data["val"] = $val;

		//ページネーションを設定するため、表示させれデータの全件数をcount関数で取得します
		$total = count(Model_Mybbs::find("all"));


		//ページネーションの設定用変数を作成します。
		$config = array(
				"pagination_url" => "http://dev3.m-craft.com/matsui/mc_kadai/kadai_fuel/bbs/index",
				"url_segment" => 3,	//セグメント指定(bbs/indexの次の階層にページ指定)※bbsはコントローラー
				"per_page" => 3,	//1ページでの表示件数
				"num_links" =>10,
				"total_items" => $total,
				"link_offset" => 1,
		);


		// 'mypagination' という名前の pagination インスタンスを作る
		$pagination = Pagination::forge('mypagination', $config);
		//モデルからデータを取得
		$data['posts'] = Model_Mybbs::find('all',array(
						'order_by' => array(
							'created_at' => 'desc'
						),
						'limit' => $pagination->per_page,
						'offset' => $pagination->offset
				)
		);

		return View::forge('bbs/index',$data);

	}
}