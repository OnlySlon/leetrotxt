<?php
//
// LeetroTXT, Leetro laser controller Intermediate TXT format parser & player php class
// I Do It Just for Fun
// (C) Vadim Melikhow(uprsnab@gmail.com)
// 23.05.2017
//
// Notes:
// Now support cut and engrave modes.
// Engraving tested with 2-way engrave mode
// Bug: Can't understand how to calculate engraving offset (see $HZZ value in code) 

class LeetroTXT
{
    private $subs;                      // TXT SUB database 
	private $log_enable = true;         // Store logs
	private $log_buffer = [];
	private $y_mirror = true;           // Mirror Y axis
	private $im;                        // Imagemagic context
	private $draw;                      // Imagemagic draw context
	private $image_w = 1280;            // Default image width
	private $image_h = 720;             // Default image height
	private $draw_syspoints = false;    // Draw startup points, engrave debug points
	private $out_filename = "test.png"; // Default output filename
	private $min_x = 100500;            
	private $max_x = 0;
	private $min_y = 100500;
	private $max_y = 0;
	private $scale = 15;
	private $y_off = 0;
	private $x_off = 0;	
	private $cmds_loaded = false;


	const SUB_MODE_NONE           = 0;
    const SUB_MODE_INSIDE         = 1;     


	public function Log2text()
	{
		$out = "";
		foreach($this->log_buffer as $rec)
		{
			$out .= $rec."\n";
		}
		return $out;
	}



	private function Log($str)
	{
		if (!$this->log_enable) return;
		$trace=debug_backtrace(); 
		$fname =  $trace[1]["function"];
		$this->log_buffer[] = $fname.": ".$str;
	}

	public function imageSet($filename = 'test.png', $iw = 1080, $ih = 720)
	{
		if (strlen($filename))
			$this->out_filename = $filename;

		// image res can be changed only before cmd's is loaded
		if (!$this->cmds_loaded)
		{
			$this->image_w  = $iw;
			$this->image_h  = $ih;
		}
		return;
	}

    public function LoadTXT($filename = "TEST.TXT")
    {
		$idx = 0;
		$sub_id = null;
		$current_sub_id = null;
		$sub_mode = LeetroTXT::SUB_MODE_NONE;
		$sub_loaded = 0;
		$cmds_loaded = 0;

		if ($file = fopen($filename, "r")) 
		{
			$this->Log("File '$filename' loaded...");
			while(!feof($file))
			{
				$line = fgets($file);               
				list($cmd) =  explode(",", $line);
				$cmd = trim($cmd);
		
				if (substr($cmd, 0, 3) == "SUB")
				{
					$sub_id = substr($cmd, 3, strlen($cmd) - 3);
					if ($sub_mode == LeetroTXT::SUB_MODE_NONE)
					{
						$this->Log("FIND SUB #".$sub_id." START");
						$current_sub_id = $sub_id;
						$idx = 0;
						$sub_mode = LeetroTXT::SUB_MODE_INSIDE;
					} else
					{
						if ($current_sub_id == $sub_id)
						{
							$this->Log("FIND SUB #".$sub_id." FINISH");
							$sub_mode = LeetroTXT::SUB_MODE_NONE;
							$current_sub_id = null;
							$sub_loaded++;
						}
					}
				}

				if ($sub_mode == LeetroTXT::SUB_MODE_INSIDE  && substr($cmd, 0, 3) != "SUB")
				{
					$argsc = count(explode(",", $line));
					$args  = explode(",", $line);
					$this->subs[$current_sub_id][$idx]['cmd'] = trim($args[0]);
					for($i=1;$i<$argsc;$i++)
						$this->subs[$current_sub_id][$idx]['arg'.$i] = trim($args[$i]);
					$idx++;
					$cmds_loaded++;

					if ($cmd == "CMD001") // determine min-max coords
					{
						$x = (int) $args[1];
						$y = (int) $args[2];
						$this->min_x = ($x < $this->min_x) ? $x : $this->min_x;
						$this->min_y = ($y < $this->min_y) ? $y : $this->min_y;
						$this->max_x = ($x > $this->max_x) ? $x : $this->max_x;
						$this->max_y = ($y > $this->max_y) ? $y : $this->max_y;
    				}
				}
       
			} // while
			$this->Log("Loaded ".$sub_loaded." SUB('s) and ".$cmds_loaded." commands");
			$dx = ($this->max_x - $this->min_x);
			$dy = ($this->max_y - $this->min_y);
			$scale_x = $dx / $this->image_w;
			$scale_y = $dy / $this->image_h;
			$this->scale = ($scale_y > $scale_x) ? $scale_y : $scale_x;
			$this->x_off = -($this->min_x / $this->scale);
			$this->y_off = -($this->min_y / $this->scale);
			// Center by axis
			if ($scale_y < $scale_x)
				$this->y_off += ($this->image_h - ($dy / $this->scale)) / 2;
			else
				$this->x_off += ($this->image_w - ($dx / $this->scale)) / 2;

			
			$this->Log("Draw square: ".$this->min_x." ". (int)$this->min_y." --> ".$this->max_x." ".$this->max_y);
			$this->cmds_loaded = true;
			
			fclose($file);
		}
    }

	// translate internal coordinates to view
	private function coord($xi, $yi)
	{
			$x = round(($xi/$this->scale) + $this->x_off);
			if ($this->y_mirror)		
				$y = $this->image_h - round($yi/$this->scale + $this->y_off);
			else
				$y = round($yi/$div + $this->y_off);

			return array($x, $y);
	}


	private function DrawInit()
	{
		$this->Log("Imagemagic init...");
		$string = " Leetro TXT parser"; 
		$this->im = new Imagick(); 
		$this->draw = new ImagickDraw(); 
		$this->im->setPointSize(10);
		$this->draw->setFillColor(new ImagickPixel('black')); 
		$this->draw->setFontSize(28); 
		$this->draw->annotation(0,25,$string); 
		$this->im->newImage($this->image_w, $this->image_h , new ImagickPixel('white')); 
		return;
	}
	

	public function Draw()
	{
		$sx = null;
		$sy = null;
		$laser = false; // laser is on

		$this->DrawInit();
		$this->Log("Lets Draw...");

		foreach ($this->subs as $skey => $sub)
		{
			$this->Log("Work with SUB #".$skey);
			foreach($sub as $ckey => $cmd)
			{
				$known = false;
				if ($cmd['cmd'] == "CMD106")
				{
					$this->Log(">> CMD006(CHANGE_POWER) ".$cmd['arg1']);;
					$known = true;
				}

				if ($cmd['cmd'] == "CMD001")
				{
					list($x,$y) = $this->coord($cmd['arg1'], $cmd['arg2']);
					$known = true;
					if ($laser)
					{
						$this->Log(">> CMD001(DRAWLINE) TO $x $y");
						$this->draw->setStrokeColor("black");
						$this->draw->line($sx, $sy, $x, $y);
					} else
					{
						$this->Log(">> CMD001(DRAWLINE-INVISIBLE) TO $x $y");
					}			
					$sx = $x;
					$sy = $y;
				}

				if ($cmd['cmd'] == "CMD002")
				{
					// Startpoint
					$known = true;
					list($x,$y) = $this->coord($cmd['arg1'], $cmd['arg2']);
					if ($this->draw_syspoints)
					{
						$this->draw->setStrokeColor("black"); 
						$this->draw->setFillColor("#00FF00"); 
						$this->draw->circle($x-1, $y-1, $x+1, $y+1);
					}
					$sx = $x;
					$sy = $y;
					$xhz = $cmd['arg1'];
					$yhz = $cmd['arg2'];
					$this->Log(">> CMD002(STARTPOINT) $x $y");
				}


				if ($cmd['cmd'] == "CMD050")
				{
					// MASHINE CONTROL
					$known = true;
					$a = "OFF";
					if ($cmd['arg2'] == "1" ) $a = "ON";
					switch($cmd['arg1'])
					{
						case '1':
							$sub = "LASER#1";
							$laser = $cmd['arg2'];
							break;
						case '2':
							$sub = "BLOW";
							break;
						case '5':
							$sub = "FIXME";
							break;
						default:
							$sub = "FIXME";
							break;	
					}
					$this->Log(">> CMD050(MASHINECTRL) SUB:$sub(".$cmd['arg1'].") CMD:$a");
				}

				/*
					Engraving control points
				*/
				if ($cmd['cmd'] == "CMD005" || $cmd['cmd'] == "CMD006")
				{
					$known = true;
					if ($cmd['arg1'] == 1)  $xhz = $cmd['arg2'];
					if ($cmd['arg1'] == 2)  $yhz = $cmd['arg2'];

					if ($this->draw_syspoints && $xhz != null && $yhz != null)
					{
						list($x,$y) = $this->coord($xhz, $yhz);
						$this->draw->setStrokeColor("blue");	
						$this->draw->setFillColor("blue");
						$this->draw->circle($x, $y, $x+2, $y+2);
					}
				}

				/*
				    Engraving
				*/
				if ($cmd['cmd'] == "CMD406")
				{
					static $ccnt = 0;
					$known = true;
					static $right = true;
					$argsc = count($cmd) - 1;
					for($i=0;$i<$argsc;$i++)
					{
						if ($i == 0)
						{
							$arg = dechex($cmd['arg1']);
							$hz1 = hexdec(substr($arg, -4));  // Backlash offset!!!!
							$hz2 = hexdec(substr($arg, 0, strlen($arg) - 4));  // HZ
							//				echo ">>>>CMD406 IDX".$ccnt." SETUP ARG=$arg   $hz1 $hz2\n";				
						}
						else
						{
							$arg = $cmd["arg".($i+1)];
							$drw  = hexdec(substr($arg, -4)); 
							$skip = hexdec(substr($arg, 0, strlen($arg) - 4));
							$dir =  $right?"RIGHT":"LEFT";
							$this->Log(">>>>CMD406 IDX".$ccnt."  #".$i."=".$arg." skip=$skip  drw=$drw DIR=".$dir);
							//				$HZZ = 3700;
							$HZZ = 2425;
							$off = $HZZ + $hz1;
							if ($right)
							{
								//					continue;
								list($dx, $dy)   = $this->coord($xhz + $off,         $yhz);	
								list($dxd, $dyd) = $this->coord($xhz + $off + $drw,  $yhz);	
								$this->Log(">>>>CMD406 LINE RIGHT $dx.$dy --> $dxd.$dyd");
								$this->draw->setStrokeColor("red");
								$this->draw->setFillColor("red");
								//					$draw->circle($dx, $dy, $dx+1, $dy+1);
								$this->draw->line($dx, $dy, $dxd, $dyd);
								$xhz+= $skip + $drw;
							} else
							{
								list($dx, $dy)   = $this->coord($xhz - $off,           $yhz);	
								list($dxd, $dyd) = $this->coord(($xhz - $off) - $drw,  $yhz);
								$this->Log(">>>>CMD406 LINE LEFT $dx.$dy --> $dxd.$dyd");
								$this->draw->setStrokeColor("red");
								$this->draw->setFillColor("red");
								$this->draw->line($dx, $dy, $dxd, $dyd);
								$this->draw->setFillColor("orange");
								//					$draw->circle($dxd, $dyd, $dxd+2, $dyd+2);
								$xhz -= ($drw + $skip);
							}
							$ccnt++;
						}
					} // for($i=0;$i<$argsc;$i++)
					$right = $right ? false : true;
					$xhz = null;
					$yhz = null;
				}

				if ($cmd['cmd'] == 'CMD402')
				{
					$percent =  (($cmd['arg1'] / 125) + 459) / 9000;
					$this->Log(">> CMD402(INSTANT_LASER_POWER) ".$percent."%");
				}
			}
		}

		$this->im->drawImage($this->draw); 
		$this->im->borderImage(new ImagickPixel('black'), 1, 1); 
		$this->im->setImageFormat('png'); 
		$this->im->writeImage("./test.png"); 
	}

}






?>
