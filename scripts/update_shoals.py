#!/usr/bin/env python3
"""Update The Shoals.md with indigenous perspective"""

def update_shoals_file():
    with open('The Shoals.md', 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Add new section after "protected for future generations."
    old_text1 = "It is important to be guardians of a vanished culture. It should be further studied and protected for future generations.\nChickasaws and Cherokees"
    new_text1 = """It is important to be guardians of a vanished culture. It should be further studied and protected for future generations.
The Shoals as Indigenous Crossroads
     Long before European contact, the Shoals region served as a vital meeting place and crossroads for Indigenous nations. The Five Civilized Tribes—Cherokee, Chickasaw, Choctaw, Creek (Muscogee), and Seminole—along with other nations including the Yuchi, regularly gathered at the Shoals for trade, diplomacy, and cultural exchange. The area's strategic location at the natural river crossing made it neutral ground where different peoples could meet peacefully.
     The Chickasaw language became the lingua franca of trade throughout the Tennessee Valley and beyond. Traders from diverse nations used Chickasaw to conduct business, negotiate treaties, and communicate across cultural boundaries. This linguistic prominence reflected the Chickasaw nation's central role in regional commerce and diplomacy.
     The river itself provided not just transportation but sustenance—abundant fish, mussels, and waterfowl supplemented agricultural harvests. The shoals' unique ecology created fishing opportunities that drew seasonal gatherings, strengthening bonds between nations through shared resources and ceremonial practices.
Chickasaws and Cherokees"""
    
    if old_text1 in content:
        content = content.replace(old_text1, new_text1)
        print("✓ Added Indigenous Crossroads section")
    
    # Update Chickasaws and Cherokees intro
    old_text2 = "     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees.     The Cherokees were"
    new_text2 = "     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees, though understanding their presence requires acknowledging the complex web of Indigenous relationships that predated colonial boundaries.\n     The Cherokees were"
    
    if old_text2 in content:
        content = content.replace(old_text2, new_text2)
        print("✓ Updated Chickasaws and Cherokees intro")
    
    # Update Colbert family section - emphasize Scottish heritage
    old_text3 = "     The rise of the Colberts can be attributed to James Logan Colbert, who, according to Trace historian Dawsan A. Phelps, was a Scotsman living among the Chickasaws as early as 1767."
    new_text3 = "The Scottish Colbert Family and Indigenous Leadership\n     Arrell M. Gibson, author of \"The Chickasaws,\" said that by 1800 mixed bloods dominated the Chickasaw tribe and the Colberts were the most dominant.     The rise of the Colberts can be attributed to James Logan Colbert, a Scottish trader who, according to Trace historian Dawsan A. Phelps, was living among the Chickasaws as early as 1767.     James Logan Colbert's integration into Chickasaw society through marriage to Chickasaw women created a family that would bridge two worlds, becoming powerful leaders within the Chickasaw nation while maintaining connections to Euro-American commerce and politics."
    
    # Remove duplicate sentence before it
    content = content.replace("     Arrell M. Gibson, author of \"The Chickasaws,\" said that by 1800 mixed bloods dominated the Chickasaw tribe and the Colberts were the most dominant.     The rise of the Colberts can be attributed to James Logan Colbert, who, according to Trace historian Dawsan A. Phelps, was a Scotsman living among the Chickasaws as early as 1767.", old_text3)
    print("✓ Updated Colbert family section")
    
    # Update George Colbert info
    old_text4 = "     Colbert had at least five sons who all rose to prominence in the tribe.     The eldest was reputed to be George"
    new_text4 = "     Colbert had at least five sons who all rose to prominence in the tribe, becoming influential leaders who navigated the complex political landscape between Indigenous sovereignty and American expansion.\n     The eldest was reputed to be George"
    
    if old_text4 in content:
        content = content.replace(old_text4, new_text4)
        print("✓ Updated Colbert sons description")
    
    # Add ferry infrastructure context
    old_text5 = " He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819.     Colbert became widely known"
    new_text5 = " He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819.     George Colbert's ferry became essential infrastructure at the Shoals crossing, serving travelers on the Natchez Trace and facilitating the movement of people and goods through Chickasaw territory.\n     Colbert became widely known"
    
    if old_text5 in content:
        content = content.replace(old_text5, new_text5)
        print("✓ Added ferry infrastructure context")
    
    # Add Trail of Tears section after George Colbert's removal
    old_text6 = "     After 1820, he moved back to Mississippi and eventually went to Oklahoma in 1837 on the Chickasaw removal.\nLevi Colbert takes over"
    new_text6 = """     After 1820, he moved back to Mississippi and eventually went to Oklahoma in 1837 on the Chickasaw removal.
The Trail of Tears and River Crossings
     The forced removal of Indigenous peoples from their ancestral lands, known as the Trail of Tears, brought thousands through the Shoals region in the 1830s. Multiple crossing points were used along the Tennessee River, from the traditional fords at the Shoals downstream to Waterloo, which became a major embarkation point.     Waterloo's deep water made it suitable for loading displaced Cherokee, Chickasaw, Choctaw, Creek, and other nations onto boats for the western journey.
     The Scottish-Chickasaw Colbert family, having once controlled the ferry crossings that facilitated peaceful trade and travel, witnessed their own people being forced westward along these same routes.     The river that had once been a meeting place for the Five Civilized Tribes became a corridor of sorrow, as tens of thousands were driven from their homes.     Many who crossed at Waterloo and the Shoals never completed the journey, succumbing to disease, exposure, and heartbreak along the way.
Levi Colbert takes over"""
    
    if old_text6 in content:
        content = content.replace(old_text6, new_text6)
        print("✓ Added Trail of Tears section")
    
    # Update Donelson expedition - acknowledge Indigenous use
    old_text7 = " These may have been the first American families to use McFarland Bottoms as a campground.     After four months"
    new_text7 = " These may have been the first American families to use McFarland Bottoms as a campground—though Indigenous peoples had used these crossing points and camp sites for thousands of years.     The Shoals and areas downriver as far as Waterloo had long served as major crossing points, and Waterloo would later become a tragic departure point during the Trail of Tears.\n     After four months"
    
    if old_text7 in content:
        content = content.replace(old_text7, new_text7)
        print("✓ Updated Donelson expedition context")
    
    # Update Waterloo section
    old_text8 = "     During the Indian removal, knows as the Trail of Tears, Waterloo was a major site for shipping American Indians west to the Oklahoma Territory. Dendy said Waterloo was destroyed twice"
    new_text8 = """     During the Indian removal, known as the Trail of Tears, Waterloo was a major embarkation site for shipping American Indians west to the Oklahoma Territory. The town's deep-water port made it tragically suitable for loading displaced families onto boats.     Cherokee, Chickasaw, Choctaw, and Creek people—nations that had met peacefully at the Shoals for generations—were forcibly gathered here for deportation.     The suffering witnessed at Waterloo during the removals of the 1830s represents one of the darkest chapters in the region's history, transforming a place once associated with Indigenous trade and diplomacy into a point of departure for ethnic cleansing.
     Dendy said Waterloo was destroyed twice"""
    
    if old_text8 in content:
        content = content.replace(old_text8, new_text8)
        print("✓ Updated Waterloo section")
    
    # Write updated content
    with open('The Shoals.md', 'w', encoding='utf-8') as f:
        f.write(content)
    
    print("\n✓ File updated successfully!")

if __name__ == '__main__':
    update_shoals_file()
