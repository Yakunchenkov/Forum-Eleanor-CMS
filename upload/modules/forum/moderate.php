<?php
/*
	Copyright � Eleanor CMS
	URL: http://eleanor-cms.ru, http://eleanor-cms.com
	E-mail: support@eleanor-cms.ru
	Developing: Alexander Sunvas*
	Interface: Rumin Sergey
	=====
	*Pseudonym
*/

class ForumModerate extends Forum
{
	{
		$data+=array(
			'trash'=>$this->GetOption('trash'),#�������� �����
			'language'=>Language::$main,
			'makenewtrash'=>false,#���������, ��� ��� ����������� � �������, ����� ������� ����� ����, ���� ���� ����� ���� ��� ����������
		);
		$in=Eleanor::$Db->In($ids);
		$repf=$rtids=array();#Repair forums & topics
		if($data['trash'])
		{
			if(!$trash=$R->fetch_assoc())
				throw new EE('NO_FORUM_TRASH',EE::INFO);

			$repf=array($trash['id']=>array($trash['language']));

			$fltp=$ttr=$need=$newtr=array();#Forum-language-topic-post & to-trash % need new trash topic & new trash topic
			$R=Eleanor::$Db->Query('SELECT `p`.`id`,`p`.`f`,`p`.'t',`p`.`status`,`t`.`language`,`t`.`status` `tstatus`,'.($data['makenewtrash'] ? '0 `trid`,0 `trstatus`' : '`tr`.`id` `trid`,`tr`.`status` `trstatus`').' FROM `'.$this->config['fp'].'` `p` INNER JOIN `'.$this->config['ft'].'` `t` ON `t`.`id`=`p`.'t''.($data['makenewtrash'] ? '' : ' LEFT JOIN `'.$this->config['ft'].'` `tr` ON `t`.`trash`=`tr`.`id`').' WHERE `p`.`id`'.$in.' ORDER BY `p`.`sortdate` ASC');
			while($a=$R->fetch_assoc())
			{
				$repf[$a['f']][]=$a['language'];
				$rtids[]=$a['t'];

				$fltp[$a['f']][$a['language']][$a['t']][]=array($a['id'],$a['status']);
				if($a['trid'])
					$ttr[$a['t']]=array($a['trid'],$a['trstatus']);
				else
					$need[]=$a['t'];
			}

			Eleanor::$Db->Transaction();
			if($need)
			{
				while($a=$R->fetch_assoc())
				{
					$a['views']=$a['posts']=$a['moved_to']=0;
					$a['trash']=$a['id'];
					$a['f']=$trash['id'];
					$a['language']=$trash['language'];
					unset($a['id'],$a['url']);
					$rtids[]=$ttr[$a['trash']]=array(Eleanor::$Db->Insert($this->config['ft'],$a),$a['status']);
					$newtr[$ttr[$a['trash']][0]]=true;
					Eleanor::$Db->Update($this->config['ft'],array('trash'=>$ttr[$a['trash']][0]),'`id`='.$a['trash'].' LIMIT 1');
				}
			foreach($fltp as $f=>&$lt)
				foreach($lt as $l=>&$tp)
				{
					foreach($tp as $tid=>&$dposts)
					{
						$in=array();

						$first=false;
						foreach($dposts as $k=>&$v)
						{
							if(in_array($v[1],array(1,-2)))
								$p++;
							elseif(in_array($v[1],array(-1,-3)))
								$sp++;
							if($first===false)
							{
										$ttr[$tid][1]=0;
									elseif($ttr[$tid][1]==-1 and $p>0)
										$ttr[$tid][1]=1;
									elseif($ttr[$tid][1]==1 and $sp>0)
										$ttr[$tid][1]=-1;

								if($p>0)
									$first=1;
								elseif($sp>0)
									$first=-1;
								else
									$first=0;
							$in[]=$v[0];
						}
						$fp+=$p;
						$fsp+=$sp;

						$in=Eleanor::$Db->In($in);
						Eleanor::$Db->Update($this->config['fp'],array('f'=>$trash['id'],'language'=>$trash['language'],'!status'=>'(CASE `status` WHEN '.($ttr[$tid][1]==1 ? '-2 THEN 1 WHEN -3 THEN -1' : '1 THEN -2 WHEN -1 THEN -3').' ELSE `status` END)','t'=>$ttr[$tid][0]),'`id`'.$in);
						Eleanor::$Db->Update($this->config['fa'],array('f'=>$trash['id'],'language'=>$trash['language'],'t'=>$ttr[$tid][0]),'`p`'.$in);
						if($p+$sp>0)
							Eleanor::$Db->Update($this->config['ft'],array('!posts'=>'GREATEST(0,`posts`-'.$p.')','!queued_posts'=>'GREATEST(0,`queued_posts`-'.$sp.')'),'`id`='.$tid.' LIMIT 1');

						if(isset($newtr[$ttr[$tid][0]]))
							switch($first)
							{
									$p--;
									$fmp--;
								break;
								case -1:
									$sp--;
									$fmsp--;
								break;

						if($p+$sp>0)
							Eleanor::$Db->Update($this->config['ft'],array('!posts'=>'`posts`+'.$p,'!queued_posts'=>'`queued_posts`+'.$sp),'`id`='.$ttr[$tid][0].' LIMIT 1');
					}
					if($fp+$fsp>0)
						Eleanor::$Db->Update($this->config['fl'],array('!posts'=>'GREATEST(0,`posts`-'.$fp.')','!queued_posts'=>'GREATEST(0,`queued_posts`-'.$fsp.')'),'`id`='.$f.' AND `language`=\''.$l.'\' LIMIT 1');

					if(isset($newtr[$ttr[$tid][0]]) and $ttr[$tid][1])
						if($ttr[$tid][1]==1)
							$add=array('!topics'=>'`topics`+1');
						else
							$add=array('!queued_topics'=>'`queued_topics`+1');
						$add=array();
					Eleanor::$Db->Update($this->config['fl'],array('!posts'=>'`posts`+'.($fp+$fmp),'!queued_posts'=>'`queued_posts`+'.($fsp+$fmsp))+$add,'`id`='.$trash['id'].' AND `language`=\''.$trash['language'].'\' LIMIT 1');
				}
			Eleanor::$Db->Commit();
		}
		else
		{
			while($a=$R->fetch_assoc())
			{
				$rtids[]=$a['t'];
				$tposts[$a['t']]=array($a['queued_posts'],$a['posts']);
				$repf[$a['f']][]=$a['language'];
			}
			$this->DeleteAttach($in,'p');
			Eleanor::$Db->Transaction();
			Eleanor::$Db->Delete($this->config['fp'],'`id`'.$in);
			foreach($fltp as $f=>&$lt)
				foreach($lt as $l=>&$tp)
				{
					foreach($tp as $tid=>&$dposts)
					{
						$p=$sp=0;
						$in=array();
						foreach($dposts as $k=>&$v)
						{
							if(in_array($v[1],array(1,-2)) and $tposts[$tid][1]>0)
							{
								$p+=1;
								$tposts[$tid][1]--;
							}
							elseif(in_array($v[1],array(-1,-3)) and $tposts[$tid][0]>0)
							{
								$sp+=1;
								$tposts[$tid][0]--;
							}
							$in[]=$v[0];
						}
						$fp+=$p;
						$fsp+=$sp;
						if($f+$fp>0)
							Eleanor::$Db->Update($this->config['ft'],array('!posts'=>'GREATEST(0,`posts`-'.$p.')','!queued_posts'=>'GREATEST(0,`queued_posts`-'.$sp.')'),'`id`='.$tid.' LIMIT 1');
					}
					if($fp+$fsp>0)
						Eleanor::$Db->Update($this->config['fl'],array('!posts'=>'GREATEST(0,`posts`-'.$fp.')','!queued_posts'=>'GREATEST(0,`queued_posts`-'.$fsp.')'),'`id`='.$f.' AND `language`=\''.$l.'\' LIMIT 1');
			Eleanor::$Db->Commit();
		}
		if($rtids)
		{
			Eleanor::$Db->Update($this->config['ft'],array('!last_mod'=>'NOW()'),$rt);
			$this->RepairTopics($rt);
		}
		if($repf)
			$this->RepairForums($repf,$rtids);
	}

	/*
		������� ������� ����. ��������� ��������������� ������ � ��������� ���������.
		������� �� ������������ ����� � ����!
	*/
	protected function RepairTopics($int)
	{
		while($a=$R->fetch_assoc())
		{
			if($post=$R->fetch_assoc() and (($post['status']<0 xor $a['status']<0) or $a['author']!=$post['author'] or $a['author_id']!=$post['author_id'] or $a['created']!=$post['created']))
			{
					$post['status']=-1;
				Eleanor::$Db->Update($this->config['ft'],$post,'`id`='.$a['id'].' LIMIT 1');
				if($post['status']!=$a['status'])
				{
						$upd['!topics']='GREATEST(0,`topics`-1)';
					elseif($a['status']==-1)
						$upd['!queued_topics']='GREATEST(0,`queued_topics`-1)';

					if($post['status']==1)
						$upd['!topics']='`topics`+1';
					elseif($post['status']==-1)
						$upd['!queued_topics']='`queued_topics`+1';
					Eleanor::$Db->Update($this->config['fl'],$upd,'`id`='.$a['f'].' AND `language`=\''.$a['language'].'\' LIMIT 1');
					$a['status']=$post['status'];
					$fids[$a['f']][]=$a['language'];
					$tids[]=$a['id'];
				}
			}

			#��������� ����
			$R=Eleanor::$Db->Query('SELECT `id`,`author`,`author_id`,`created` FROM `'.$this->config['fp'].'` WHERE 't'='.$a['id'].' AND `status`='.($a['status']==1 ? 1 : -2).' ORDER BY `sortdate` DESC LIMIT 1');
			{
				$tids[]=$a['id'];
			}
		}
		if($fids)
			$this->RepairForums($fids,$tids);

	/*
		����������� �������� lp
		$fids=array(
			'IDforum'=>array('lang1','lang2'..)
		)
	*/
	public function RepairForums($fids,$tids=false)
	{
		while($a=$R->fetch_assoc())
			if(in_array($a['language'],$fids[$a['id']]))
			{
				$R=Eleanor::$Db->Query('SELECT `id` `lp_id`,`title` `lp_title`,`lp_date`,`lp_author`,`lp_author_id` FROM `'.$this->config['ft'].'` WHERE `f`=\''.$a['id'].'\' AND `language`=\''.$a['language'].'\' AND `status`=1 AND `state` IN (\'open\',\'closed\') ORDER BY `sortdate` DESC LIMIT 1');
				if(!$upd=$R->fetch_assoc())
					$upd=array(
						'lp_date'=>'0000-00-00 00:00:00',
						'lp_id'=>0,
						'lp_title'=>'',
						'lp_author'=>'',
						'lp_author_id'=>0,
					);
				Eleanor::$Db->Update($this->config['fl'],$upd,'`id`='.$a['id'].' AND `language`=\''.$a['language'].'\' LIMIT 1');
			}

	/*
		��������� �������� ���������� ���
	*/
	protected function KillEmptyTopics($int)
	{
		$R=Eleanor::$Db->Query('SELECT `t`.`id`,`t`.`f`,`t`.`language`,`t`.`status` FROM `'.$this->config['ft'].'` `t` LEFT JOIN `'.$this->config['fp'].'` `p` ON `p`.'t'=`t`.`id` WHERE `t`.'.$int.' AND `t`.`state` IN (\'open\',\'closed\') AND `p`.'t' IS NULL');
		while($a=$R->fetch_assoc())
		{
			if($a['status']>0)
				$updft[$a['f']][$a['language']]=isset($updft[$a['f']][$a['language']]) ? $updft[$a['f']][$a['language']]+1 : 1;
			elseif($a['status']<0)
				$updfqt[$a['f']][$a['language']]=isset($updfqt[$a['f']][$a['language']]) ? $updfqt[$a['f']][$a['language']]+1 : 1;
			$delt[]=$a['id'];
		}
		if($delt)
		{
			Eleanor::$Db->Delete($this->config['ts'],''t''.$in);
			Eleanor::$Db->Delete($this->config['ft'],'`id`'.$in);
			Eleanor::$Db->Delete($this->config['ft'],'`moved_to`'.$in);
			foreach($updft as $f=>&$langs)
				foreach($langs as $lang=>&$cnt)
				{
					{
						$inact=array('!queued_topics'=>'GREATEST(0,`queued_topics`-'.$updfqt[$f][$lang].')');
						unset($updfqt[$f][$lang]);
					else
					Eleanor::$Db->Update($this->config['fl'],array('!topics'=>'GREATEST(0,`topics`-'.$cnt.')')+$inact,'`id`='.$f.' AND `language`=\''.$lang.'\' LIMIT 1');
				}

			foreach($updfqt as $f=>&$langs)
				foreach($langs as $lang=>&$cnt)
					Eleanor::$Db->Update($this->config['fl'],array('!queued_topics'=>'GREATEST(0,`queued_topics`-'.$cnt.')'),'`id`='.$f.' AND `language`=\''.$lang.'\' LIMIT 1');
		}

	/*
		$ids - ��� ������
		$to - �� ����, ���� �����������
	*/
	public function MovePost($ids,$to)
	{
		$inp='`id`'.$in;

		$R=Eleanor::$Db->Query('SELECT `id`,`f`,`language`,`status` FROM `'.$this->config['ft'].'` WHERE `id`='.$to.' LIMIT 1');
		if(!$topic=$R->fetch_assoc())
			throw new EE('NO_TOPIC',EE::INFO);

		$uforums=$utopics=array();
		#������ ���������� ����������� ������ �� ������
		$R=Eleanor::$Db->Query('SELECT `f`,`language`,`status`,COUNT(`status`) `cnt` FROM `'.$this->config['fp'].'` WHERE '.$inp.' AND `status`!=0 AND (`f`!='.$topic['f'].' OR `language`!=\''.$topic['language'].'\') GROUP BY `f`,`language`');
		while($a=$R->fetch_assoc())
			$uforums[$a['f']][$a['language']]=array(in_array($a['status'],array(-2,1)) ? 1 : -1,$a['cnt']);

		#������ ���������� ����������� ������ �� ���
		$R=Eleanor::$Db->Query('SELECT 't',`status`,COUNT(`status`) `cnt` FROM `'.$this->config['fp'].'` WHERE '.$inp.' AND `status`!=0 AND 't'!='.$topic['id'].' GROUP BY 't'');
		while($a=$R->fetch_assoc())
			$utopics[$a['t']]=array(in_array($a['status'],array(-2,1)) ? 1 : -1,$a['cnt']);

		Eleanor::$Db->Transaction();
		Eleanor::$Db->Update($this->config['fp'],array('f'=>$topic['f'],'language'=>$topic['language'],'t'=>$topic['id']),$inp);
		Eleanor::$Db->Update($this->config['fa'],array('f'=>$topic['f'],'language'=>$topic['language'],'t'=>$topic['id']),'`p`'.$in);

		#��������� ���������� ������ � �������
		$p=$sp=0;
		foreach($uforums as $fid=>&$langs)
			foreach($langs as $lang=>&$data)
			{
					$p+=$data[1];
				else
					$sp+=$data[1];#suspended
				Eleanor::$Db->Update($this->config['fl'],$data[0]==1 ? array('!posts'=>'GREATEST(0,`posts`-'.$data[1].')') : array('!queued_posts'=>'GREATEST(0,`queued_posts`-'.$data[1].')'),'`f`='.$fid.' AND `language`=\''.$lang.'\' LIMIT 1');
			}
		Eleanor::$Db->Update($this->config['fl'],array('!posts'=>'`posts`+'.$p,'!queued_posts'=>'`queued_posts`+'.$sp),'`f`='.$topic['f'].' AND `language`=\''.$topic['language'].'\' LIMIT 1');

		#��������� ���������� ������ � �����
		$p=$sp=0;
		foreach($utopics as $tid=>&$data)
		{
			if($data[0]==1)
				$p+=$data[1];
			else
				$sp+=$data[1];
			Eleanor::$Db->Update($this->config['ft'],$data[0]==1 ? array('!posts'=>'GREATEST(0,`posts`-'.$data[1].')') : array('!queued_posts'=>'GREATEST(0,`queued_posts`-'.$data[1].')'),'`id`='.$tid.' LIMIT 1');
		}
		Eleanor::$Db->Update($this->config['ft'],array('!posts'=>'`posts`+'.$p,'!queued_posts'=>'`queued_posts`+'.$sp),'`id`='.$to.' LIMIT 1');
		Eleanor::$Db->Commit();

		if($utopics)
		{
			$int='`id`'.Eleanor::$Db->In(array_keys($utopics));
			Eleanor::$Db->Update($this->config['ft'],array('!last_mod'=>'NOW()'),$int);
			$this->KillEmptyTopics($int);
			$this->RepairTopics($int);
		}
	}

	/*
		����������� ���
		$ids - ��� ���
		$to - �� ������,���� �����������
	*/
	public function MoveTopic($ids,$to,$data=array())
	{
			return;
		$data+=array(
			'trash'=>$this->GetOption('trash'),#�� �������
			'makenewtrash'=>false,#���������, ��� ��� ����������� � �������, ����� ������� ����� ����, ���� ���� ����� ���� ��� ����������
			'moved'=>false,#�������� ������ ������ �� ����
			'language'=>Language::$main,#���� ������, ���� ���������� ����
			'who_moved'=>'',#����� �����������
			'who_moved_id'=>0,
			'when_moved'=>date('Y-m-d H:i:s'),
		);
		$R=Eleanor::$Db->Query('SELECT `id`,`language`,`lp_id` FROM `'.$this->config['fl'].'` WHERE `id`='.$to.' AND `language`'.($data['language'] ? 'IN(\'\',\''.$data['language'].'\')' : '=\'\'').' LIMIT 1');
		if(!$dest=$R->fetch_assoc())
			throw new EE('NO_FORUM',EE::INFO);

		$btotr=$dest['id']==$data['trash'];

		$R=Eleanor::$Db->Query('SELECT `id`,`f`,`status`,`language`,`state`,`trash`,`posts`,`queued_posts` FROM `'.$this->config['ft'].'` WHERE `id`'.Eleanor::$Db->In($ids).' AND `f`!='.$dest['id']);
		$restore=$fids=$ids=$uforums=$totr=array();
		while($a=$R->fetch_assoc())
		{
				$restore[$a['id']]=$a['trash'];
			elseif($btotr and $a['trash'])
				$totr[$a['trash']][$a['id']]=array('status'=>$a['status'],'posts'=>$a['posts'],'queued_posts'=>$a['queued_posts'],'language'=>$a['language'],'f'=>$a['f']);

			if($a['status']!=0)
			{
				if(!isset($uforums[$a['f']][$a['language']]))
					$uforums[$a['f']][$a['language']]=array(0,0,0,0,0,0,0,0);#queued posts, posts, topics, queued topics
				$uforums[$a['f']][$a['language']][0]+=$a['queued_posts'];
				$uforums[$a['f']][$a['language']][1]+=$a['posts'];
				if($a['status']==1)
					$uforums[$a['f']][$a['language']][2]+=1;
				else
					$uforums[$a['f']][$a['language']][3]+=1;
			}
		}

		Eleanor::$Db->Transaction();
		if($restore)
		{
			while($a=$R->fetch_assoc())
			{
				if($a['f']==$dest['id'] and $dest['language']==$a['language'])
				{

					$R=Eleanor::$Db->Query('SELECT SUM(`posts`),SUM(`queued_posts`) FROM `'.$this->config['ft'].'` WHERE `id`'.$in);
					list($p,$sp)=$R->fetch_row();

					Eleanor::$Db->Update($this->config['fp'],array('f'=>$a['f'],'language'=>$a['language'],'t'=>$a['id']),''t''.$in);
					Eleanor::$Db->Delete($this->config['ft'],'`id`'.$in);
					Eleanor::$Db->Delete($this->config['ft'],'`moved_to`'.$in);

					Eleanor::$Db->Update($this->config['ft'],array('trash'=>0,'!posts'=>'`posts`+'.$p,'!queued_posts'=>'`queued_posts`+'.$sp),'`id`='.$a['id'].' LIMIT 1');
				}
				else
					$ids=array_merge($ids,$tids);
			}
		}

		if($btotr)
		{
			foreach($totr as $k=>&$v)
			{
				{
					continue;
				if($R->num_rows()>0)
				{
					{
						$sp+=$topic['queued_posts'];
						#������ ��������� ������� ���� - ���������� ���������� ����.
						if($topic['status']==1)
						{
							$p++;
							$uforums[$topic['f']][$topic['language']][5]++;
							$uforums[$topic['f']][$topic['language']][6]--;
						}
						elseif($topic['status']==-1)
						{
							$sp++;
							$uforums[$topic['f']][$topic['language']][4]++;
							$uforums[$topic['f']][$topic['language']][7]--;
						}
					}
					$in=Eleanor::$Db->In(array_keys($v));
					Eleanor::$Db->Update($this->config['ft'],array('!posts'=>'`posts`+'.$p,'!queued_posts'=>$sp),'`id`='.$k.' LIMIT 1');
					Eleanor::$Db->Update($this->config['fp'],array('f'=>$dest['id'],'language'=>$dest['language'],'t'=>$k),''t''.$in);
					Eleanor::$Db->Update($this->config['fa'],array('f'=>$dest['id'],'language'=>$dest['language'],'t'=>$k),''t''.$in);
					Eleanor::$Db->Delete($this->config['ts'],''t''.$in);
					Eleanor::$Db->Delete($this->config['ft'],'`id`'.$in);
					Eleanor::$Db->Delete($this->config['ft'],'`moved_to`'.$in);
				}
				else
					$ids=array_merge($ids,array_keys($v));
			}
		}
		if($ids)
		{
			Eleanor::$Db->Delete($this->config['ft'],'`moved_to`'.$in.' AND `f`='.$dest['id'].' AND `language`=\''.$dest['language'].'\'');
			{
				while($a=$R->fetch_assoc())
				{
					$a['state']='moved';
					$a['who_moved']=$data['who_moved'];
					$a['who_moved_id']=$data['who_moved_id'];
					$a['when_moved']=$data['when_moved'];
					$a['sortdate']=$a['lp_date'];
					unset($a['id'],$a['url'],$a['pinned']);
					$movedt[]=$a;
				if($movedt)
					Eleanor::$Db->Insert($this->config['ft'],$movedt);
			}
			Eleanor::$Db->Update($this->config['ft'],array('f'=>$dest['id'],'language'=>$dest['language']),'`id`'.$in);
			Eleanor::$Db->Update($this->config['fp'],array('f'=>$dest['id'],'language'=>$dest['language']),''t''.$in);
			Eleanor::$Db->Update($this->config['fa'],array('f'=>$dest['id'],'language'=>$dest['language']),''t''.$in);
		}

		$p=$sp=$t=$st=0;
		foreach($uforums as $fid=>&$langs)
			foreach($langs as $l=>&$dat)
			{
				$sp+=$dat[0]+$dat[4];#suspended
				$p+=$dat[1]+$dat[5];
				$t+=$dat[2]+$dat[6];
				$st+=$dat[3]+$dat[7];#suspended
				Eleanor::$Db->Update($this->config['fl'],array('!posts'=>'GREATEST(0,`posts`-'.$dat[1].')','!queued_posts'=>'GREATEST(0,`queued_posts`-'.$dat[0].')','!topics'=>'GREATEST(0,`topics`-'.$dat[2].')','!queued_topics'=>'GREATEST(0,`queued_topics`-'.$dat[3].')'),'`id`='.$fid.' AND `language`=\''.$l.'\' LIMIT 1');
			}
		Eleanor::$Db->Update($this->config['fl'],array('!posts'=>'`posts`+'.$p,'!queued_posts'=>'`queued_posts`+'.$sp,'!topics'=>'`topics`+'.$t,'!queued_topics'=>'`queued_topics`+'.$st),'`id`='.$dest['id'].' AND `language`=\''.$dest['language'].'\' LIMIT 1');
		Eleanor::$Db->Commit();

		if($fids)
		{
			$ids[]=$dest['lp_id'];
			$this->RepairForums($fids,$ids);
		}
	}

	/*
		�������� ���
		$ids - ��� ���.
	*/
	public function DeleteTopic($ids,$data=array())
	{
		$data+=array(
			'trash'=>$this->GetOption('trash'),#ID ������
			'deltrash'=>false,#������� ����, ���� ��� ��� � �������
			'language'=>Language::$main,#���� ������, ���� ���������� ����
		);
		$R=Eleanor::$Db->Query('SELECT `id`,`f`,`status`,`language`,`state`,`posts`,`queued_posts` FROM `'.$this->config['ft'].'` WHERE `id`'.Eleanor::$Db->In($ids));
		$ids=$del=$uforums=array();
		while($a=$R->fetch_assoc())
		{
			if($data['trash'] and $a['f']!=$data['trash'])
				$ids[]=$a['id'];
			else
			{
					continue;
				if($a['status']!=0 and $act)
				{
					if(!isset($uforums[$a['f']][$a['language']]))
						$uforums[$a['f']][$a['language']]=array(0,0,0,0);#queued posts, posts, topics, queued topics
					$uforums[$a['f']][$a['language']][0]+=$a['queued_posts'];
					$uforums[$a['f']][$a['language']][1]+=$a['posts'];
					if($a['status']==1)
						$uforums[$a['f']][$a['language']][2]++;
					elseif($a['status']==-1)
						$uforums[$a['f']][$a['language']][3]++;
				}
			}
		}
		if($data['trash'])
			$this->MoveTopic($ids,$data['trash'],$data);
		if($del)
		{
			$in=Eleanor::$Db->In($del);
			$this->DeleteAttach($in,'t');
			Eleanor::$Db->Transaction();
			Eleanor::$Db->Delete($this->config['ts'],''t''.$in);
			Eleanor::$Db->Delete($this->config['fp'],''t''.$in);
			Eleanor::$Db->Delete($this->config['ft'],'`id`'.$in);
			Eleanor::$Db->Delete($this->config['ft'],'`moved_to`'.$in);
			$fids=array();
			foreach($uforums as $fid=>&$langs)
			{
				foreach($langs as $lang=>&$dat)
					Eleanor::$Db->Update($this->config['fl'],array('!posts'=>'GREATEST(0,`posts`-'.$dat[1].')','!queued_posts'=>'GREATEST(0,`queued_posts`-'.$dat[0].')','!topics'=>'GREATEST(0,`topics`-'.$dat[2].')','!queued_topics'=>'GREATEST(0,`queued_topics`-'.$dat[3].')'),'`id`='.$fid.' AND `language`=\''.$lang.'\' LIMIT 1');
				$fids[$fid]=array_keys($langs);
			}
			Eleanor::$Db->Commit();
			$this->RepairForums($fids,$ids);
		}
	}

	{
			return;
		$delp=true;
		switch($t)
		{
				$in='`f`'.$in;
			break;
			case'p':
				$in='`p`'.$in;
			break;
				$in='`t`'.$in;
			break;
			default:
				$in='`id`'.$in;
				$delp=false;
		}
		if($delp)
		{
			while($a=$R->fetch_assoc())
				Files::Delete(Eleanor::$root.Eleanor::$uploads.DIRECTORY_SEPARATOR.$this->config['n'].'/p'.$a['p']);
		}
		else
		{
			while($a=$R->fetch_assoc())
			{
				if($a['file'])
					Files::Delete($root.'/p'.$a['p'].DIRECTORY_SEPARATOR.$a['file']);
				if($a['preview'])
					Files::Delete($root.'/p'.$a['p'].DIRECTORY_SEPARATOR.$a['preview']);
			}
		Eleanor::$Db->Delete($this->config['fa'],$in);
	}
#####

	/*
		���������� ����
	*/
	public function UpdateTopic($ids,$data)
	{

	/*
		���������� ��������� � ��� ����������� ����
	*/
	public function UpdatePost($ids,$data)
	{
		#ToDo! ���������� ������ ����������� ����� ������, ������� ���������
	}

	/*
		������� ���� � ����� ���
		������ ��, ������������ � $ids - �� ����, � ������� �������� ��� ���������
	*/
	public function MergeTopics(array $ids,$data=array())
	{
			'per_attach'=>10000,#����� �������, ������������ �� ���
			'movesubs'=>false,#����������� �������� �� ����. ����� ���� �������� UID=>true (���������� ��� ��� ��������)
		);
		#ToDo! ������� ��� �������� �� ������� ����
	}

	/*
		������� ���� � ����� ���������
	*/
	public function MergePosts(array$ids,$data=array())
	{
		#ToDo!
	}

	/*
		�������� ��������� �� ID
	*/
	public function DeleteReputation($ids)
	{