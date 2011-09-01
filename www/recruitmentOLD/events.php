<?php
session_start();
include_once('../php/login.php');
$authUsers = array('admin', 'recruitment');
include_once('../php/authenticate.php');

include_once($_SERVER['DOCUMENT_ROOT']."/includes/headerFirst.php"); ?>

<link type="text/css" href="/styles/ui-lightness/jquery-ui-1.8.1.custom.css" rel="stylesheet" />
<link type="text/css" href="/styles/popUp.css" rel="stylesheet" />

<script type="text/javascript" src="/js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="/js/jquery-ui-1.8.1.custom.min.js"></script>
<script type="text/javascript" src="/js/popup.js"></script>

<script>

//Create popups for Notification, Service, and House Hours
$(document).ready(function(){
	
	$("a.members").click(function(){
		
		$.get('attendancePopup.php?type=member', function(data){
			$("#popupBody").html(data);
			
			$(".closeWindow").click(function(){
				disablePopup('#generalPopup');
			});
		});
		
		//$('#generalPopup').css('width', '420px');
		
		//centering with css
		centerPopup('#generalPopup');
		//load popup
		loadPopup('#generalPopup');
	});
	
	$(".assign").click(function(){
		
		var id = $(this).attr('id');
		
		$.get('assignPopup.php?ID=' + id, function(data){
			$("#popupBody").html(data);
			
			$(".submitAssign").click(function(){
				var assignID = $(this).attr('id');
				var assignTo = $("#assignTo").val();
				
				window.location = 'recruitAction.php?action=assign&id=' + assignID + '&value=' + assignTo;
			});
		});
		
		//$('#generalPopup').css('width', '420px');
		
		//centering with css
		centerPopup('#generalPopup');
		//load popup
		loadPopup('#generalPopup');
	});
	
	$(".remove").click(function(){
		var id = $(this).attr('id');
		
		window.location = 'recruitAction.php?action=remove&id=' + id;
	});
		
	//CLOSING  POPUP
	//Click the x event!
	$('#popupClose').click(function(){
		disablePopup('#generalPopup');
	});
	//Click out event!
	$("#backgroundPopup").click(function(){
		disablePopup('#generalPopup');
	});
});

</script>

<?php include_once($_SERVER['DOCUMENT_ROOT']."/includes/headerLast.php"); ?>

<?php
		$mysqli = mysqli_connect($db_host, $db_username, $db_password, $db_database);
		
		if(isset($_GET['term']) && isset($_GET['year']))
		{
			$year = $_GET['year'];
			$term = $_GET['term'];
		} else {
			$year = date(Y);
			$month = date(n);
			
			if($month <= 7){
				$term = "spring";
			} else {
				$term = "fall";
			}
		}
		
		$termTable = $term.$year;
		
		$eventData = "
			SELECT * 
			FROM events 
			WHERE type='recruitment'
			AND term ='".$termTable."'
			ORDER BY eventDate DESC";
		$getEventData = mysqli_query($mysqli, $eventData);
	?>
		
	<div style="text-align:center;">
		
		 <h2> Recruitment Events - <?php echo ucwords($term)." ".$year; ?></h2>
		
		<table width="600" border="0" cellspacing="0" cellpadding="0" align="center">
			<tr> 
				<td><div align="right"><a href="<? 
	  		
			if($term == "fall"){
				echo "events.php?year=$year&amp;term=spring&amp;type=$type"; 
			} else {
				$lastYear = $year-1;
				echo "events.php?year=$lastYear&amp;term=fall&amp;type=$type"; 
			}
			
			?>">&lt;&lt;</a></div></td>
				<td width="200"><div align="center">
					
					<select name="term" id="month" onChange="MM_jumpMenu('parent',this,0)">
						<?
			if($term == "fall"){
		  		echo "<option value=\"events.php?year=$year&amp;term=spring&amp;type=$type\" >Spring</option>\n";
				echo "<option value=\"events.php?year=$year&amp;term=fall&amp;type=$type\" selected>Fall</option>\n";
			} else {
				echo "<option value=\"events.php?year=$year&amp;term=spring&amp;type=$type\" selected>Spring</option>\n";
				echo "<option value=\"events.php?year=$year&amp;term=fall&amp;type=$type\" >Fall</option>\n";
			}
			?>
						</select>
					<select name="year" id="year" onChange="MM_jumpMenu('parent',this,0)">
						<?
		  $yearLoop = date("Y");
		  
		  for ($i = $yearLoop+1; $i >= $yearLoop-3; $i--) {
		  	if($i == $year){
				$selected = "selected";
			} else {
				$selected = "";
			}
		  	echo "<option value=\"events.php?term=$term&amp;year=$i&amp;type=$type\" $selected>$i</option>\n";
		  }
		  ?>
						</select>
					</div></td>
				<td><div align="left"><a href="<? 
	  	
		if($term == "fall"){
				$nextYear = $year+1;
				echo "events.php?year=$nextYear&amp;term=spring&amp;type=$type"; 
			} else {
				echo "events.php?year=$year&amp;term=fall&amp;type=$type"; 
			}
		
		?>">&gt;&gt;</a></div></td>
				</tr>
			</table>
	</div>
		
	<?php
		echo "<table>";
		$count=0;
		while($eventDataArray = mysqli_fetch_array($getEventData, MYSQLI_ASSOC)){
			$date = $eventDataArray['eventDate'];
			$date = strtotime($date);;
			$date = date("D n/j", $date);
			
			$attending = 0;
			$notAttending = 0;
			$excused = 0;
			$limbo = 0;
			
			$data = "
				SELECT status, COUNT(ID) AS num
				FROM eventAttendance 
				WHERE eventID = '$eventDataArray[ID]' 
				GROUP BY status";
			$getData = mysqli_query($mysqli, $data);
			while($dataArray = mysqli_fetch_array($getData, MYSQLI_ASSOC)){
				
				if($dataArray[status] == 'attending'){
					$attending = $dataArray[num];
				} else if($dataArray[status] == 'notAttending'){
					$notAttending = $dataArray[num];
				} else if($dataArray[status] == 'excused'){
					$excused = $dataArray[num];
				} else if($dataArray[status] == 'limbo'){
					$limbo = $dataArray[num];
				}
				
			}			
			
			echo "<tr>";
			echo "<td style=\"width: 350px;\"><b>".$eventDataArray['title']."</b> - ".$date."</td>";
			echo '<td style="text-align:right;"><a class="members" href="#">Members</a> </td>';
			echo "<td>( <b>A:</b> ".$attending."</td>";
			echo "<td><b>N:</b> ".$notAttending."</td>";
			echo "<td><b>E:</b> ".$excused."</td>";
			echo "<td><b>?:</b> ".$limbo." )</td>";
			echo "</tr>";
			
			echo '<tr>';
			echo '<td> </td>';
			echo '<td style="text-align:right;"><a href="#">Recruits</a> </td>';
			echo "<td>( <b>A:</b> ".$attending."</td>";
			echo "<td><b>N:</b> ".$notAttending."</td>";
			echo "<td><b>E:</b> ".$excused."</td>";
			echo "<td><b>?:</b> ".$limbo." )</td>";
			echo '</tr>';
			
			echo '<tr><td colspan="6">&nbsp;</td></tr>';
			$count++;
		}
		
		if($count == 0){
			echo "<p>No Events Scheduled</p>";
		}
		
		echo "</table>"
	?>
	<p style="text-align:center; padding-top: 20px;"><b>A:</b> Attending | <b>N:</b> Not Attending | <b>E:</b> Excused | <b>?</b> Awaiting Reply</p>
	<div id="generalPopup">
		<div id="popupBody">Body</div>
	</div>
	
	<div id="backgroundPopup"></div>
	
<?php include_once($_SERVER['DOCUMENT_ROOT']."/includes/footer.php"); ?>