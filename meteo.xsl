<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/current">
	<h1>Informations météo de <xsl:value-of select="city/@name"/></h1>
	<p>Temperature: 
	<xsl:value-of select="((temperature/@value - 273))"/>
	<!-- Temperature en Kelvin en dégrès -->
	°C</p>
	<p>Temps: <xsl:value-of select="weather/@value"/></p>
	<p>Humidité: <xsl:value-of select="humidity/@value"/>%</p>
	<p>Pression: <xsl:value-of select="pressure/@value"/> hPa</p>
  </xsl:template>

</xsl:stylesheet>