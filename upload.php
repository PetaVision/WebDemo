<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>PetaVision Image Classifier</title>
<link rel="icon" href="favicon.ico" type="image/x-icon" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
<script type="text/JavaScript">

var img;
// Create the canvas according to browser window size
function init(){
    var width = window.innerWidth * .98;
    var height = window.innerHeight * .98;
    var canvas = document.getElementById("canvas");
    var context = canvas.getContext("2d");
    img = new Image();
    // When the image is loaded, set the proper aspect ratio, and add image to canvas 
    img.onload = function() {
        if ((width/height) < (img.width/img.height)){
            height = (width/img.width) * img.height;
        } else if ((width/height) > (img.width/img.height)){
            width  = (height/img.height) * img.width;
        }
        canvas.setAttribute("width", width);
        canvas.setAttribute("height", height);
        context.drawImage(this, 0, 0, width, height);
        // If the user clicks on the montage image, display the image
        canvas.addEventListener('click',function(evt){
            window.open(url)
        },false);
        //TODO: Add resize listener to redraw canvas if browser is resized
    };
    drawMontage();
}

// Init is called after the php code in the body has been executed,
// so url should be set to one of the template images, and drawMontage 
// will display the image in the canvas.
function drawMontage(){
    if(imageExists(url)){
	// Setting the image.src this way causes the image in the canvas 
	// to update. Arbitrary time var added to avoid caching issues.
        img.src = url + "?t=" + new Date().getTime();
	// Wait 1/2s and call myself again in case image has changed. 
        setTimeout("drawMontage()", 500); //TODO: This is dumb. Use ajax.
    }
    else{
    // When PV is finished, the back-end removes the montage image and 
    // creates the archive of frames. This uses jquery to add a link to the archive.
    $("body").prepend('<h2>The run is finished. <a href="montage/pngOutput/MontageFrames.zip">Download Output Images</a></h2>');
    }
}

// HTML request for montage image.
function imageExists(imgPath){
    var http = new XMLHttpRequest();
    http.open('HEAD', imgPath, false); 
    http.send();
    return http.status != 404;
}
</script>
</head>

<body style="font-family:clean;" onload="init();">
<?php
// This code will be executed when the upload.php page is loaded. Once php finishes 
// dealing with the upload and calling OpenPV, js init() is called.
$target_dir = "uploads/";
$montagePath = "montage/";
$allowedVideoTypes = array("video/mp4", "video/x-ms-wmv", "video/x-msvideo", "video/mpeg");
$allowedImageTypes = array("image/png", "image/jpeg");
$isVideo = FALSE;
$im = FALSE;

// If there is an file in the uploads directory, quit
if(!empty(glob($target_dir . "*.*"))){
    exit("The server is currently busy processing another submisison. Please try again in a few minutes.");
}

// Remove the archive of frames from previous run
unlink($montagePath."pngOutput/MontageFrames.zip");

// Submitted file is an upload.
if (!$_FILES["fileToUpload"]["error"] && isset($_POST["fileSubmit"])){
    $fileHash = md5_file($_FILES["fileToUpload"]["tmp_name"]) . time();
    $fileType = $_FILES["fileToUpload"]["type"];
    if (in_array($fileType, $allowedVideoTypes)){
        $isVideo = TRUE;
    }elseif (in_array($fileType, $allowedImageTypes)){
        $im = imagecreatefromstring(file_get_contents($_FILES["fileToUpload"]["tmp_name"])); 
    }else{exit("File is not a valid upload type.");}
// Submitted file is a url.
}elseif (!empty($_POST["urlToUpload"] && isset($_POST["urlSubmit"]))){
    $fileHash = md5(file_get_contents($_POST["urlToUpload"])) . time(); 
    $im = imagecreatefromstring(file_get_contents($_POST["urlToUpload"]));
}else{exit("Error: No file selected.");}

$montage = $montagePath . $fileHash . ".png";
copy($montagePath . "templates/" . $_POST['model'] . ".png", $montage); 

// File is an IMAGE
if($im !== FALSE && $isVideo == FALSE){
    $target_file = $target_dir . $fileHash . ".png";
    imagepng($im, $target_file);
    // imagepng should fail if target_file is not an image. Remove original upload.
    imagedestroy($im);

    /// Sets javascript varible url to the path to the montage image
    ?><script>var url = "<?php echo $montage ?>";</script><?php

    $PV_CALL = "(cd LIVE && echo '" . getcwd() . "/" . $target_file . "' | mpirun -np 4 demos/HeatMapLocalization/HeatMapLocalization -p input/WEB_VID_" . $_POST['model'] . ".params -d 0,1,2,3 -rows 1 -columns 4 -t 8 2>&1 > webDemo.log)";

    // This PHP code needs to finish for the js to execute (because of the body onload=).
    // So the call to PV is piped into /dev/null so PHP doesn't wait for PV to finish, and
    // a separate script is called to deal with PV output independently.    
    exec($PV_CALL . " > /dev/null &"); 
    exec("/usr/bin/php monitor.php " . $target_file . " " . $fileHash . " " . $montagePath . " " . $isVideo . " > /dev/null &");
    
//// File is a VIDEO
}elseif($isVideo == TRUE){
    $extension = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);
    $target_file = $target_dir . $fileHash . "." . $extension;
    copy($_FILES["fileToUpload"]["tmp_name"], $target_file);

    /// Sets javascript varible url to the path to the montage image
    ?><script>var url = "<?php echo $montage ?>";</script><?php

    $PV_CALL = "(cd LIVE && echo '" . getcwd() . "/" . $target_file . "' | mpirun -np 4 demos/HeatMapLocalization/HeatMapLocalization -p input/WEB_VID_" . $_POST['model'] . ".params -d 0,1,2,3 -rows 1 -columns 4 -t 8)";
    exec($PV_CALL . " > /dev/null &"); 
    exec("/usr/bin/php monitor.php " . $target_file . " " . $fileHash . " " . $montagePath . " > /dev/null &");

}else{exit("This file is either corrupt or is not an image.");}
?>
<canvas id="canvas"/>
</body>
</html>
