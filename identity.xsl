<?xml version="1.0" ?>

<!-- IdentityTransform -->
    
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    
    <!--match all record nodes to take on the bootstrap card-->
    <xsl:template match="record">
        <record class="card shadow mb-4">
            <xsl:apply-templates select="@* | node()" />
        </record>
    </xsl:template>
    
    <!--match all caption_ nodes to take on the bootstrap card header-->
     <xsl:template match="caption_">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
               <xsl:apply-templates select="@* | node()" /> 
            </h6>
        </div>
    </xsl:template>
    
    <!--match all table nodes with bootstrap table tags-->
    <xsl:template match="tabled">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                   <xsl:value-of select="name"/>
                </h6>
            </div>
            <div class="card-body">
            <div class="table-responsive">
                <xsl:apply-templates select="table" />
            </div>
        </div>
        </div>
    </xsl:template>
    <xsl:template match="table">
        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
            <xsl:apply-templates select="@* | node()" />
        </table>
    </xsl:template>
   
    
    <!-- match any node with a picture element to be placed inside a div with a tag img-->
    <xsl:template match="picture">
        <div>
            <img src="pictures/{@name}"/>
        </div>
    </xsl:template> 
    
    <!-- match any node with a icon element to have a tag img-->
    <xsl:template match="icon">
        <img src="pictures/{@name}"/>
    </xsl:template>
    <!-- match any service item to have a button with an anchor a tag -->
    <xsl:template match="service_item">
        <button>
            <xsl:apply-templates select="@* | node()" />
        </button>
    </xsl:template>
    
    <!-- Match any node, viz, root, attribute or element. Does this include the
    processing instructions?-->
    <xsl:template match="/ | @* | node()">
        <!-- Copy the current node -->
        <xsl:copy>
            <!-- Apply templates to all any child node--> 
            <xsl:apply-templates select="@* | node()" />
        </xsl:copy>
    </xsl:template>
</xsl:stylesheet>