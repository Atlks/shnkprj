<?php
namespace EasySwoole\Redis\CommandHandle;

use EasySwoole\Redis\CommandConst;
use EasySwoole\Redis\Redis;
use EasySwoole\Redis\Response;

class BgRewriteAof extends AbstractCommandHandle
{
	public $commandName = 'BgReWriteAof';


	public function handelCommandData(...$data)
	{
		$command = [CommandConst::BGREWRITEAOF];
		$commandData = array_merge($command,$data);
		return $commandData;
	}


	public function handelRecv(Response $recv)
	{
		return $recv->getData();
	}
}
