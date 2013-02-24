<?php
/*
	Copyright � Eleanor CMS
	URL: http://eleanor-cms.ru, http://eleanor-cms.su, http://eleanor-cms.com, http://eleanor-cms.net, http://eleanor.su
	E-mail: support@eleanor-cms.ru, support@eleanor.su
	Developing: Alexander Sunvas*
	Interface: Rumin Sergey
	=====
	*Pseudonym
*/

class TaskSpecial_Forum extends BaseClass implements Task
{
		PATH='modules/forum/';

	{
		include_once Eleanor::$root.self::PATH.'core.php';
		$mc=include Eleanor::$root.self::PATH.'config.php';
		$done=true;
		$Forum=new ForumCore($mc);

		$q='SELECT `id`,`type`,`options`,`data`,`done`,`total` FROM `'.$mc['ta'].'` WHERE `status`=';
		$R=Eleanor::$Db->Query('('.$q.'\'wait\' ORDER BY `date` ASC LIMIT 1)UNION ALL('.$q.'\'process\' ORDER BY `date` ASC LIMIT 1)');
		if($a=$R->fetch_assoc())
		{
			switch($a['type'])
			{
					$data=$Forum->Service->SyncUsers(isset($a['options']['date']) ? $a['options'] : $a['data']);
					$tdone=$data['done']>=$data['total'];
					$upd=array(
						'!date'=>'NOW()',
						'status'=>$tdone ? 'done' : 'wait',
						'data'=>serialize(array('date'=>$data['date'])),
						'done'=>$data['done'],
						'total'=>$data['total'],
					);
					if($tdone)
						$upd['!finish']='NOW()';
				break;
				default:
					$upd=array(
						'data'=>serialize(array(
							'error'=>'Unknown task',
						)),
						'status'=>'error',
					);
			Eleanor::$Db->Update($mc['ta'],$upd,'`id`='.$a['id'].' LIMIT 1');

		return$done;

	public function GetNextRunInfo()
	{
}