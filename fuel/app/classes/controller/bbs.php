<?php
use Fuel\Core\Debug;
use Fuel\Core\Input;
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
				Response::redirect('http://localhost/fuelphp/bbs/index');
				//本番
				//Response::redirect('http://dev3.m-craft.com/matsui/mc_kadai/kadai_fuel/bbs/index');
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
			->add_rule("trim")
			->add_rule("required")
			->add_rule("max_length" , 200);

		//フォームからPOST送信されたか判定
		if (Input::method() === 'POST')
		{
			try
			{
				//トランザクション処理
				$db = Database_Connection::instance();
				$db->start_transaction();

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
							"item"		=> Input::post("item"),
							"ip"		=> Input::real_ip(),
					);
					//モデルオブジェクト作成
					$new = Model_Mybbs::forge($props);

					//データ保存成否判定
					if (!$new -> save())
					{
						//投稿失敗
						Session::set_flash('success', '投稿できませんでした');
					}
					else
					{
						//コミット&投稿成功
						$db->commit_transaction();
						Session::set_flash('success', '投稿しました');
					}
					//同セッションのため、コミット無でも一時的にSELECT表示されるためリダイレクト処理
					//Response::redirect('http://localhost/fuelphp/bbs/index');
					//本番
					Response::redirect('http://dev3.m-craft.com/matsui/mc_kadai/kadai_fuel/bbs/index');
				}
				else
				{
					// バリデーションNGの場合
					Session::set_flash('error', $val->show_errors());
				}	//$val->run()ここまで

			}
			catch (Exception $e)
			{
				$db->rollback_transaction();
				$data["save"] = "例外エラーが発生しました。";
			}
		}

		//ValidationオブジェクトをViewに渡す
		$data["val"] = $val;

		/**
		 *  redisの使用
		 */

		//redisのオブジェクトを作成
		$redis = new Redis();
		$redis->connect("localhost",6379);

		//削除する(rankingに格納された値をリセットする)
		$redis->delete('ranking');
		//$redis->flushall();

		//$itemcCountの配列を初期化
		$itemCount = array();

		//itemカラムの番号(1～3)の総数それぞれ取得
		for ($i=1; $i<=3; $i++)
		{
			$itemCount[$i] = count(Model_Mybbs::find('all', array(
				'where' => array(
					'item' => $i,
				),
			)));
		}

		//カテゴリ1～3にitemの数字をセット
		$userPoint = array(
			'カテゴリ１' => $itemCount[1],
			'カテゴリ２' => $itemCount[2],
			'カテゴリ３' => $itemCount[3],
		);

		// カテゴリの数をセットする
		foreach( $userPoint as $user => $point )
		{
			//取得したitemデータが格納されているカテゴリを'rannking'にセット
			$redis->zAdd('ranking', $point, $user );
		}

		// カテゴリの数が多い順に一覧を表示する('rannking'に格納された値を降順に取得)
		$ranking = $redis->zRevRange( 'ranking', 0, -1, true );

		//ランキング取得変数
		$data["itemCount"] = "";

		foreach($ranking as $user => $score )
		{
			//各カテゴリの投稿件数を取得
			$scoreData = $redis-> zScore( 'ranking', $user);
			$score++;
			//順位1位～3位を取得(総件数を取得するため「+inf」というキーワードを使用)
			$data["itemCount"] .= ($redis->zCount('ranking', $score, '+inf')+1)."位：" . $user ."&nbsp;[" .$scoreData ."件]&nbsp;/&nbsp;";
		}

		/**
		 *  memcachedの使用
		 */

		//memcachedのオブジェクトを作成
		$memcache = new Memcache;
		$memcache->connect('localhost', 11211) or die ("接続できませんでした");
		$version = $memcache->getVersion();
		$data["mem_version"] = "memcached動作中！ / サーバのバージョン: ".$version;

		//ソートの解除リンクを押下した場合はmemcacheの値を削除する
		if (Input::get('reset'))
		{
			$memcache->flush();
			$memcache->close();
		}

		//itemカラムのGET値をmemcashe保存
		switch (Input::get('item'))
		{
			case 1:
				$item = 1;
				$memcache->set('key', $item, MEMCACHE_COMPRESSED, 50);
				break;
			case 2:
				$item = 2;
				$memcache->set('key', $item, MEMCACHE_COMPRESSED, 50);
				break;
			case 3:
				$item = 3;
				$memcache->set('key', $item, MEMCACHE_COMPRESSED, 50);
				break;
		}

		//memcacheにデータが格納してあるか判定
		if ($memcache->get('key'))
		{
			//itemカラムの件数をcount関数で取得します
			$total = count(Model_Mybbs::find('all', array(
					'where' => array(
							'item' => $memcache->get('key'),
					),
			)));

			//ページネーションの設定用変数を作成します。
			$config = array(
					"pagination_url" => "http://dev3.m-craft.com/matsui/mc_kadai/kadai_fuel/bbs/index?item=" . $memcache->get('key'),	//本番
					//"pagination_url" => "http://localhost/fuelphp/bbs/index?item=" . $memcache->get('key'),	//開発
					"url_segment" => 3,	//セグメント指定(bbs/indexの次の階層である3番目にページ指定)※bbsはコントローラー
					"per_page" => 5,	//1ページでの記事表示件数
					"num_links" =>10,	//1ページでのページリンク表示数(※現在リンク中央設定のため、偶数指定時は+1)
					"total_items" => $total,
			);

			// 'mypagination' という名前の pagination インスタンスを作る
			$pagination = Pagination::forge('mypagination', $config);
			//モデルからデータを取得
			$data['posts'] = Model_Mybbs::find('all',array(
							'order_by' => array(
								'id' => 'desc'
							),
							'where' => array(
								'item' => $memcache->get('key'),
							),
							'limit' => $pagination->per_page,
							'offset' => $pagination->offset,
					)
			);
			//memcacheを閉じる
			$memcache->close();
		}
		else
		{
			//ページネーションを設定するため、表示させれデータの全件数をcount関数で取得します
			$total = count(Model_Mybbs::find("all"));

			//ページネーションの設定用変数を作成します。
			$config = array(
					"pagination_url" => "http://dev3.m-craft.com/matsui/mc_kadai/kadai_fuel/bbs/index",	//本番
					//"pagination_url" => "http://localhost/fuelphp/bbs/index",	//開発
					"url_segment" => 3,	//セグメント指定(bbs/indexの次の階層である3番目にページ指定)※bbsはコントローラー
					"per_page" => 5,	//1ページでの記事表示件数
					"num_links" =>10,	//1ページでのページリンク表示数(※現在リンク中央設定のため、偶数指定時は+1)
					"total_items" => $total,
			);

			// 'mypagination' という名前の pagination インスタンスを作る
			$pagination = Pagination::forge('mypagination', $config);
			//モデルからデータを取得
			$data['posts'] = Model_Mybbs::find('all',array(
					'order_by' => array(
							'id' => 'desc'
					),
					'limit' => $pagination->per_page,
					'offset' => $pagination->offset,
					)
			);
		}

		return View::forge('bbs/index',$data);

	}
}