'Following is a sample of the expected arguments
' --hostname=pso-plui IpAddress 10.1.40.104 VarbindData1 611071197 VarbindData2 1.3.6.1.4.1.2021.251.1 VarbindData3 Dave VarbindOID1 sysUpTime.0 VarbindOID2 snmpTrapOID.0 VarbindOID3 sysContact.0

'===================================================
'			Replace these variables
'===================================================
' MonitoringStationHost is the where the up.time Monitoring Station is installed.
' Ensure you can get to it from where this script is executed.
MonitoringStationHost="localhost"
' MonitoringStationPort is the API port that up.time uses
' The default is 9996
MonitoringStationPort="9996"
' The severity of the alert when an SNMP Trap is received
' 0=OK, 1=Warning, 2=Critical, 3=Unknown
AlertStatus=2
'===================================================


ExternalMonitorName="SNMP-Trap"



DEBUG_SWITCH="OFF"

Dim VarbindData()
Dim VarbindOID()


IF DEBUG_SWITCH = "ON" Then
	strFile = "C:\PROGRA~2\UPTIME~1\uptime\scripts\SNMP_T~1\Trap_Receiver.log"	
	' Create the File System Object
	Set objFSO = CreateObject("Scripting.FileSystemObject")
	Const ForAppending = 8
	Set objTextFile = objFSO.OpenTextFile(strFile, ForAppending, True)
	objTextFile.WriteLine("--------------------------------------------------------------------------------------------")
	objTextFile.WriteLine(Now)
	objTextFile.WriteLine("**********Arguments start:" & Wscript.Arguments.Count)
	numVar=0
	For Each arg In Wscript.Arguments
		objTextFile.WriteLine(arg)
		If InStr(arg, "VarbindOID") > 0 Then
			numVar = numVar + 1
		End If
	Next
	objTextFile.WriteLine("**********Arguments ends")
End If


'How many varbinds was passed
IF DEBUG_SWITCH = "ON" Then
	objTextFile.WriteLine("Number of variables:" & numVar)
End If
ReDim VarbindData(numVar)
ReDim VarbindOID(numVar)


If Wscript.Arguments.Count = 0 Then
    ' Do Proceed as Normal
	objTextFile.WriteLine("nothing")
Else
    For i = 0 to Wscript.Arguments.Count - 1
	
		If InStr(Wscript.Arguments(i), "hostname") > 0 Then				    
			tmpVar=Split(Wscript.Arguments(i),"=")
			Hostname=tmpVar(1)			
		ElseIf InStr(Wscript.Arguments(i), "IpAddress") > 0 Then				    
			IpAddress=Wscript.Arguments(i+1)
			i = i + 1
		ElseIf InStr(Wscript.Arguments(i), "VarbindOID") > 0 Then
			' The variable index is part of the flag
			' e.g. VarbindOID3 is the 3rd variable
			varIndex=Split(Wscript.Arguments(i),"VarbindOID")
			vIndex = varIndex(1)-1			
			VarbindOID(vIndex)=Wscript.Arguments(i+1)
			i = i + 1
		ElseIf InStr(Wscript.Arguments(i), "VarbindData") > 0 Then
			' The variable index is part of the flag
			' e.g. VarbindData3 is the 3rd variable
			varIndex=Split(Wscript.Arguments(i),"VarbindData")
			vIndex = varIndex(1)-1
			' If this is true, that means there is no varbind data.  Therefore, set to null.
			If InStr(Wscript.Arguments(i+1), "VarbindData") > 0 Then
				VarbindData(vIndex)=" "
			Else
				VarbindData(vIndex)=Wscript.Arguments(i+1)
				i = i + 1
			End If
		End If
    Next
End If

IF DEBUG_SWITCH = "ON" Then
	objTextFile.WriteLine("Hostname=" & Hostname)
	objTextFile.WriteLine("IpAddress=" & IpAddress)
End If

alertMessage = ""
For i = 0 to numVar -1
 	IF DEBUG_SWITCH = "ON" Then
		objTextFile.WriteLine("i=" & i & " VarbindOID=" & VarbindOID(i))
		objTextFile.WriteLine("i=" & i & " VarbindData=" & VarbindData(i))
	End If
	alertMessage = alertMessage & VarbindOID(i) & "=" & VarbindData(i) & " "
Next

alertMessage=URLEncode(alertMessage)

IF DEBUG_SWITCH = "ON" Then
	objTextFile.WriteLine("encoded alertMessage=" & alertMessage)
	objTextFile.WriteLine("http://"&MonitoringStationHost&":"&MonitoringStationPort&"/command?command=externalcheck&name="&ExternalMonitorName&"&status="&AlertStatus&"&message="&alertMessage&"&hostname="& Hostname & Chr(34))
	objTextFile.Close
End If

'Sends it off to Monitoring Station
ExternalMonitorName=URLEncode(ExternalMonitorName)

Dim alertURL
alertURL = "http://"&MonitoringStationHost&":"&MonitoringStationPort&"/command?command=externalcheck&name="&ExternalMonitorName&"&status="&AlertStatus&"&message="&alertMessage&"&hostname="& Hostname
Set objExplorer = WScript.CreateObject _
("InternetExplorer.Application", "IE_")
objExplorer.Navigate alertURL
'Determines if the window is visible or not
objExplorer.Visible = 0
'Suspend the script for 1 minute
WScript.Sleep 1500
'Close the IE (instantiated) window
objExplorer.quit


Function URLEncode(ByVal str)
 Dim strTemp, strChar
 Dim intPos, intASCII
 strTemp = ""
 strChar = ""
 For intPos = 1 To Len(str)
  intASCII = Asc(Mid(str, intPos, 1))
  If intASCII = 32 Then
   strTemp = strTemp & "+"
  ElseIf ((intASCII < 123) And (intASCII > 96)) Then
   strTemp = strTemp & Chr(intASCII)
  ElseIf ((intASCII < 91) And (intASCII > 64)) Then
   strTemp = strTemp & Chr(intASCII)
  ElseIf ((intASCII < 58) And (intASCII > 47)) Then
   strTemp = strTemp & Chr(intASCII)
  Else
   strChar = Trim(Hex(intASCII))
   If intASCII < 16 Then
    strTemp = strTemp & "%0" & strChar
   Else
    strTemp = strTemp & "%" & strChar
   End If
  End If
 Next
 URLEncode = strTemp
End Function

