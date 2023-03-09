<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    
    <xsl:output method="html"/>
    
    
  
    <xsl:template match="record">
        <!--test for caption_-->
        <xsl:if test="caption_">
            <!-- The container for the list items. -->
            <div class='list_body'>
                <!-- Output the caption -->
                <li class="nav-item">
                    <a class="nav-link" href='#{@id}'>
                        <span>
                            <i class="fas fa-fw fa-chart-area"></i>
                            <xsl:value-of select="caption_"/>
                        </span>
                    </a>
                </li>
            </div>
        </xsl:if>
    </xsl:template>
    

    <!-- Output the records as a list of items-->
    <xsl:template match="/">
        <xsl:apply-templates/> 
    </xsl:template>
</xsl:stylesheet>
