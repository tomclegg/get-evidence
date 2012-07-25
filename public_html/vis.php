<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Visual";
$gOut["nosidebar"] = true;
$gOut["content"] = <<<EOF
			<div id="get_evidence_vis_container"> 
			
			<!--[if !IE]> --> 
				<object classid="java:get_evidence_vis.class" 
            			type="application/x-java-applet"
            			archive="get_evidence_vis.jar"
            			width="900" height="550"
            			standby="Loading Processing software..." > 
            			
					<param name="archive" value="get_evidence_vis.jar" /> 
				
					<param name="mayscript" value="true" /> 
					<param name="scriptable" value="true" /> 
				
					<param name="image" value="loading.gif" /> 
					<param name="boxmessage" value="Loading Processing software..." /> 
					<param name="boxbgcolor" value="#FFFFFF" /> 
				
					<param name="test_string" value="outer" /> 
			<!--<![endif]--> 
				
				<object classid="clsid:8AD9C840-044E-11D1-B3E9-00805F499D93" 
						codebase="http://java.sun.com/update/1.5.0/jinstall-1_5_0_15-windows-i586.cab"
						width="900" height="550"
						standby="Loading Processing software..."  > 
						
					<param name="code" value="get_evidence_vis" /> 
					<param name="archive" value="get_evidence_vis.jar" /> 
					
					<param name="mayscript" value="true" /> 
					<param name="scriptable" value="true" /> 
					
					<param name="image" value="loading.gif" /> 
					<param name="boxmessage" value="Loading Processing software..." /> 
					<param name="boxbgcolor" value="#FFFFFF" /> 
					
					<param name="test_string" value="inner" /> 
					
					<p> 
						<strong> 
							This browser does not have a Java Plug-in.
							<br /> 
							<a href="http://java.sun.com/products/plugin/downloads/index.html" title="Download Java Plug-in"> 
								Get the latest Java Plug-in here.
							</a> 
						</strong> 
					</p> 
				
				</object> 
				
			<!--[if !IE]> --> 
				</object> 
			<!--<![endif]--> 
			
			</div> 
EOF;

go();
