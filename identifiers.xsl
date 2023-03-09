<?xml version="1.0" encoding="UTF-8"?>

<!--
    Document   : identifiers.xsl
    Created on : January 31, 2022, 3:07 PM
    Author     : user
    Description:
        Purpose of transformation follows.
-->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:output method="html"/>

    <!-- TODO customize transformation rules 
         syntax recommendation http://www.w3.org/TR/xslt 
    -->
     <xsl:template match="record">
         <!-- test identifiers only-->
         <xsl:if test="identifier_">
            <!-- Output the record  -->
            <a class="collapse-item" href='#{@id}'>
                <xsl:value-of select="identifier_"/>
            </a>
         </xsl:if>
    </xsl:template>
    <!-- Do not output text nodes-->
    <xsl:template match="text()"/>
    
    <xsl:template match="/">
        
        <xsl:apply-templates/>
    </xsl:template>

</xsl:stylesheet>
