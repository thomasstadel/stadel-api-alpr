<?php
	/*
		Search for EU license plate from image
	*/
	function Stadel_ALPR($image_file)
	{
        // Temporary file for image
        $tmpfile = "/tmp/" . uniqid("Stadel_ALPR") . ".jpg";

        // Max size of image before uploading to webservice
        $x = 1000;
        $y = 1000;

        // Is the image file readable
        if (!is_file($image_file) or !is_readable($image_file)) return false;

        // Automatic downscaling the image
        if (preg_match("/\.(jpg|jpeg)$/i", $image_file)) {
            // JPG
            if (!$img = imagecreatefromjpeg($image_file)) return false;
        }
        elseif (preg_match("/\.png$/i", $image_file)) {
            // PNG
            if (!$img = imagecreatefrompng($image_file)) return false;
        }
        else {
            // Unknown format - implement more?
            return false;
        }
        if (imagesx($img) > 1000 or imagesy($img) > 1000)
        {
			$img_x = imagesx($img);
			$img_y = imagesy($img);
			$ny_x = $img_x;
			$ny_y = $img_y;
			if ($ny_x > $x)
			{
				$ny_x = $x;
				$ny_y = round($ny_x / $img_x * $img_y);
			}
			if ($ny_y > $y)
			{
				$ny_y = $y;
				$ny_x = round($ny_y / $img_y * $img_x);
			}
			if ($ny_x < $img_x or $ny_y < $img_y)
			{
				// GD Lib 2.x
				if ($img2 = @imagecreatetruecolor($ny_x, $ny_y))
				{
					// Transparent
					$color_trans = imagecolortransparent($img);
					if ($color_trans >= 0)
					{
						$color_trans = imagecolorsforindex($img, $color_trans);
						$color_trans2 = imagecolorallocatealpha($img2, $color_trans['red'], $color_trans['green'], $color_trans['blue'], 127);
					}
					else
					{
						$color_trans2 = imagecolorallocatealpha($img2, 127, 127, 127, 127);
					}
					imagecolortransparent($img2, $color_trans2);
					imagefill($img2, 0, 0, $color_trans2);
					imagesavealpha($img2, true);
					
					imagecopyresampled($img2, $img, 0, 0, 0, 0, $ny_x, $ny_y, $img_x, $img_y);
				}
				else
				{
					// GD Lib 1.x
					$img2 = imagecreate($ny_x, $ny_y);
					imagecopyresized($img2, $img, 0, 0, 0, 0, $ny_x, $ny_y, $img_x, $img_y);
				}
                $img = $img2;
            }
        }

        // Correcting rotation
        $exif = exif_read_data($image_file);
        if ($exif["Orientation"])
        {
            $rotation = 0;
            if ($exif["Orientation"] == 3) $rotation = 180;
            if ($exif["Orientation"] == 6) $rotation = -90;
            if ($exif["Orientation"] == 8) $rotation = 90;
            
            if ($rotation != 0)
            {
                $img = imagerotate($img, $rotation, 0);
            }
        }

        // Saving temporary image
        if (!imagejpeg($img, $tmpfile)) return false;
        imagedestroy($img);
        
        // Base64 of image
        $image_base64 = base64_encode(file_get_contents($tmpfile));

        // Delete temporary image
        unlink($tmpfile);

		// Connecting to webservice
		if ($fs = fsockopen("ssl://api.stadel.dk", 443, $a, $b, 1))
		{
			// Connection OK
			$content = json_encode(array(
				"key" => "INSERT-YOUR-KEY-HERE",
				"image_base64" => $image_base64
				));
			fputs($fs, "POST /alpr.php HTTP/1.0\r\n" .
				"Host: api.stadel.dk\r\n" .
				"Connection: close\r\n" .
				"Content-type: application/x-www-form-urlencoded\r\n" .
				"Content-Length: " . strlen($content) . "\r\n" .
				"\r\n" .
				$content);
			$timeout = microtime(true) + 3;
			$res = "";
			while ($timeout > microtime(true) and !feof($fs))
			{
				$res .= fgets($fs, 1024);
			}
			fclose($fs);
			
			$res = substr($res, strpos($res, "\r\n\r\n") + 4);
			
			$json = json_decode($res);
			
			if ($json->status == "ok")
			{
				// Great, we got a license plate
				return $json->results[0];
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
    }
    
    /*
        How to use
    */
    if ($regno = Stadel_ALPR("car.jpg")) {
        echo("Found licenseplate: " . $regno . PHP_EOL);
    }
    else {
        echo("No results" . PHP_EOL);
    }