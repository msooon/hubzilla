<?php


function starred_init(&$a) {

	$starred = 0;

	if(! local_channel())
		killme();
	if(argc() > 1)
		$message_id = intval(argv(1));
	if(! $message_id)
		killme();

	$r = q("SELECT item_flags FROM item WHERE uid = %d AND id = %d LIMIT 1",
		intval(local_channel()),
		intval($message_id)
	);
	if(! count($r))
		killme();

	$item_starred = (intval($r[0]['item_starred']) ? 0 : 1);

	$r = q("UPDATE item SET item_starred = %d WHERE uid = %d and id = %d",
		intval($item_starred),
		intval(local_channel()),
		intval($message_id)
	);

	header('Content-type: application/json');
	echo json_encode(array('result' => $item_starred));
	killme();
}
