<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    
    <xsl:output method="html"/>
    
    <xsl:template match="service">
        <!-- Output the services offered -->
        <a class="collapse-item" href='#{@id}'>
            <xsl:value-of select="name"/>
        </a>
    </xsl:template>
   
    <!-- Do not output text nodes-->
    <xsl:template match="text()"/>

    <!-- Outout the records as a list of items-->
    <xsl:template match="/">
            <h6 class="collapse-header">Mutall Services</h6>
            <xsl:attribute name="onclick">myfunction()</xsl:attribute>
        <xsl:apply-templates/> 
        
    </xsl:template>

</xsl:stylesheet>
