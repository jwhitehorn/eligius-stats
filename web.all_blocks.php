<?php
/*Copyright (C) 2011 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Artefact2\EligiusStats;

require __DIR__.'/lib.eligius.php';
require __DIR__.'/inc.servers.php';

function showBlocks($address = null) {
	global $SERVERS, $LEGACY_SERVERS;
	$s = $SERVERS + $LEGACY_SERVERS;
	$now = time();

	echo "<table id=\"rfb_all\">\n<thead>\n<tr><th>▼ When</th><th>Server</th><th colspan=\"3\">Round duration</th>";
	if($address !== null) {
		echo "<th>Submitted shares</th>";
	}
	echo "<th>Total shares</th><th title=\"Total amount of BTC the server has to pay rewards, including the generated coins of this block.\"><span>Server funds</span></th><th title=\"BTC spent to pay shares from this round.\"><span>Paid in this block</span></th><th>Reward per share</th>";
	if($address !== null) {
		echo "<th>Reward</th>";
	}

	echo "<th>Status</th>";
	echo "<th>Block</th></tr>\n</thead>\n<tbody>\n";

	$success = true;
	$recent = array();
	$colors = array();
	foreach($s as $name => $kzk) {
		list($pName,) = $kzk;
		$colors[$name] = extractColor($pName);

		$blocks_r = cacheFetch('blocks_recent_'.$name, $s_r);
		$blocks_o = cacheFetch('blocks_old_'.$name, $s_o);
		$success = $success && $s_r && $s_o;
		if($s_r) {
			foreach($blocks_r as $b) {
				$b['server'] = $name;
				$recent[] = $b;
			}
		}
		if($s_o) {
			foreach($blocks_o as $b) {
				$b['server'] = $name;
				$recent[] = $b;
			}
		}
	}

	usort($recent, function($b, $a) { return $a['when'] - $b['when']; });

	if(!$success) {
		echo "<tr><td><small>N/A</small></td><td colspan=\"3\"><small>N/A</small></td><td><small>N/A</small></td><td><small>N/A</small></td><td><small>N/A</small></td><td><small>N/A</small></td>";
		if($address !== null) {
			echo "<td><small>N/A</small></td><td><small>N/A</small></td>";
		}
		echo "</tr>\n";
	} else {
		$a = 0;
		foreach($recent as $r) {
			$a = ($a + 1) % 2;

			$hash = strtoupper($r['hash']);
			$server = '<td style="background-color: '.$colors[$r['server']].';">'.$s[$r['server']][0].'</td>';

			$when = prettyDuration($now - $r['when'], false, 1).' ago';
			$shares = $r['shares_total'];
			$block = '<a href="http://'.getBE().'/block/'.$r['hash'].'" title="'.$hash.'">…'.substr($hash, -25).'</a>';

			if(isset($r['duration'])) {
				list($seconds, $minutes, $hours) = extractTime($r['duration']);
				$duration = "<td class=\"ralign\" style=\"width: 1.5em;\">$hours</td><td class=\"ralign\" style=\"width: 1.5em;\">$minutes</td><td class=\"ralign\" style=\"width: 1.5em;\">$seconds</td>";
			} else {
				$duration = "<td colspan=\"3\"><small>N/A</small></td>";
			}

			echo "<tr class=\"row$a\"><td>$when</td>$server$duration";

			if($address !== null) {
				if($r['shares'] === null && $shares > 0) {
					$myShares = '<small>N/A</small>';
				} else {
					$myShares = isset($r['shares'][$address]) ? $r['shares'][$address] : 0;
					$myShares = prettyInt($myShares);
				}
				echo "<td class=\"ralign\">$myShares</td>";
			}

			$funds = (isset($r['metadata']['funds'])) ? prettySatoshis($r['metadata']['funds']) : '<small>N/A</small>';
			$paid = (isset($r['metadata']['funds'])) ? prettySatoshis($r['metadata']['paid']) : '<small>N/A</small>';
			$rps = ($shares > 0 && isset($r['metadata']['funds'])) ? prettySatoshis($r['metadata']['paid'] / $shares) : '<small>N/A</small>';
			$shares = ($shares > 0) ? prettyInt($shares) : '<small>N/A</small>';

			echo "<td class=\"ralign\">$shares</td>";
			echo "<td class=\"ralign\">$funds</td><td class=\"ralign\">$paid</td><td class=\"ralign\">$rps</td>";

			if($address !== null) {
				if(isset($r['valid']) && $r['valid'] === false) {
					$reward = prettyBTC(0);
				} else {
					$reward = (isset($r['rewards'][$address]) ? prettyBTC($r['rewards'][$address]) : prettyBTC(0));
				}

				echo "<td class=\"ralign\">$reward</td>";
			}

			echo prettyBlockStatus($r['valid'], $r['when']);
			echo "<td class=\"ralign\">$block</td>";
		}
	}

	echo "</tbody>\n</table>\n";
}

$uri = explode('?', $_SERVER['REQUEST_URI']);
$uri = array_shift($uri);
$uri = explode('/', $uri);
$address = array_pop($uri);

if(!$address) $address = null;

printHeader('Blocks found by the Eligius pool', 'Blocks found by the pool', $relative = '..');
if($address !== null) {
	echo "<h2>Showing shares and rewards of the address : ".htmlspecialchars($address)."</h2>";
}

showBlocks($address);
printFooter($relative);
