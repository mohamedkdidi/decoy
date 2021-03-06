<? foreach($workers as $worker): ?>
	<fieldset data-js-view="worker" data-log-url=<?=route('decoy::workers@tail', strtolower(urlencode($worker->getName())))?> data-interval="<?=$worker->currentInterval('raw')?>">
		<div class="legend sidebar-header"><?=ucwords(str_replace(':', ' : ', $worker->getName())) ?>

			<div class="pull-right actions">
				<span class="status">
					<? if ($worker->isRunning() == 'ok'): ?>
						<span class="glyphicon glyphicon-ok"></span>
					<? else: ?>
						<span class="glyphicon glyphicon-question-sign"></span>
					<? endif ?>
					Rate: <strong><?=$worker->currentInterval('abbreviated')?></strong>
					</span>
				<a class="btn btn-sm outline">Logs</a>
			</div>

		</div>
		<div class="worker-entry">

			<p><?=$worker->getDescription()?></p>

			<ul>
				<li>Last worker execution: <?=$worker->lastHeartbeat()?></li>
				<li>Last heartbeat<?if(!$worker->isRunning()):?> (and execution)<?endif?>: <?=$worker->lastHeartbeatCheck()?></li>
				<li>Currently executing every: <?=$worker->currentInterval()?></li>
			</ul>

			<div class="log closed">Loading...</div>
		</div>
	</fieldset>
<? endforeach ?>
